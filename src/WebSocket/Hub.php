<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\Repository\MessageRepository;

/**
 * Verwaltet Raeume und die darin verbundenen Clients: verteilt Nachrichten,
 * setzt Rate-Limit und Laengenbegrenzung durch, persistiert Nachrichten und
 * pflegt die Online-Userliste (Presence).
 */
final class Hub
{
    private const HISTORY_LIMIT = 50;
    private const BODY_MAX_CHARS = 2000;
    private const RATE_MAX = 10;          // max. Nachrichten
    private const RATE_WINDOW = 10.0;     // pro Sekunden

    /** @var array<int, array<int, Connection>> roomId => connId => Connection */
    private array $rooms = [];

    public function __construct(private readonly MessageRepository $messages)
    {
    }

    /** Nimmt eine bereits per Handshake authentifizierte Verbindung in ihren Raum auf. */
    public function join(Connection $conn): void
    {
        $roomId = $conn->roomId;
        $this->rooms[$roomId][$conn->id] = $conn;

        // Verlauf an den neuen Client senden.
        $history = array_map(
            fn (array $m): array => [
                'username' => $m['username'],
                'body'     => $m['body'],
                'ts'       => $this->toEpoch($m['created_at']),
            ],
            $this->messages->recent($roomId, self::HISTORY_LIMIT)
        );
        $conn->sendJson(['type' => 'history', 'messages' => $history]);

        // Begruessung + aktualisierte Presence an den Raum.
        $this->broadcast($roomId, [
            'type'    => 'system',
            'message' => $conn->username . ' ist dem Raum beigetreten.',
            'ts'      => time(),
        ]);
        $this->broadcastPresence($roomId);
    }

    /** Verarbeitet eine eingehende Client-Nachricht (JSON-Text). */
    public function onMessage(Connection $conn, string $json): void
    {
        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return; // ungueltiges JSON ignorieren
        }

        if (!is_array($data) || ($data['type'] ?? null) !== 'message') {
            return;
        }

        $body = is_string($data['body'] ?? null) ? trim($data['body']) : '';
        if ($body === '') {
            return;
        }

        if (mb_strlen($body) > self::BODY_MAX_CHARS) {
            $conn->sendJson(['type' => 'error', 'message' => 'Nachricht ist zu lang (max. ' . self::BODY_MAX_CHARS . ' Zeichen).']);
            return;
        }

        if (!$conn->allowMessage(self::RATE_MAX, self::RATE_WINDOW)) {
            $conn->sendJson(['type' => 'error', 'message' => 'Du sendest zu schnell. Bitte kurz warten.']);
            return;
        }

        // Rohtext persistieren (Escaping erst bei der Ausgabe im Client).
        $this->messages->add($conn->roomId, $conn->userId, $conn->username, $body);

        $this->broadcast($conn->roomId, [
            'type'     => 'message',
            'username' => $conn->username,
            'body'     => $body,
            'ts'       => time(),
        ]);
    }

    /** Entfernt eine Verbindung und aktualisiert Presence. */
    public function leave(Connection $conn): void
    {
        $roomId = $conn->roomId;
        if (!isset($this->rooms[$roomId][$conn->id])) {
            return;
        }

        unset($this->rooms[$roomId][$conn->id]);

        $this->broadcast($roomId, [
            'type'    => 'system',
            'message' => $conn->username . ' hat den Raum verlassen.',
            'ts'      => time(),
        ]);
        $this->broadcastPresence($roomId);

        if (($this->rooms[$roomId] ?? []) === []) {
            unset($this->rooms[$roomId]);
        }
    }

    /**
     * Eindeutige Online-Benutzernamen eines Raums.
     *
     * @return list<string>
     */
    public function roster(int $roomId): array
    {
        $names = [];
        foreach ($this->rooms[$roomId] ?? [] as $conn) {
            $names[$conn->username] = true;
        }
        $list = array_keys($names);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /** @param array<string,mixed> $data */
    private function broadcast(int $roomId, array $data): void
    {
        foreach ($this->rooms[$roomId] ?? [] as $conn) {
            if ($conn->open) {
                $conn->sendJson($data);
            }
        }
    }

    private function broadcastPresence(int $roomId): void
    {
        $users = $this->roster($roomId);
        $this->broadcast($roomId, [
            'type'  => 'presence',
            'users' => $users,
            'count' => count($users),
        ]);
    }

    /** Wandelt einen UTC-DB-Zeitstempel in Epoch-Sekunden um. */
    private function toEpoch(string $dbTime): int
    {
        $ts = strtotime($dbTime . ' UTC');

        return $ts === false ? time() : $ts;
    }
}
