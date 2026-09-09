<?php

declare(strict_types=1);

namespace App\WebSocket;

/**
 * Repraesentiert eine einzelne WebSocket-Verbindung: Lesepuffer, Reassembly
 * fragmentierter Nachrichten, Identitaet (aus dem Handshake), Raumzuordnung
 * und ein Rate-Limit-Fenster. Das Schreiben erfolgt ueber einen injizierten
 * Writer, wodurch die Klasse ohne echte Sockets testbar ist.
 */
final class Connection
{
    public string $buffer = '';
    public string $assembly = '';
    public ?int $assemblyOpcode = null;
    public bool $open = true;

    /** Beliebiger Socket-Handle, den der Server zuordnet (im Test null). */
    public mixed $stream = null;

    public int $userId = 0;
    public string $username = '';
    public int $roomId = 0;

    /** @var list<float> Zeitstempel der zuletzt gesendeten Nachrichten (Rate-Limit). */
    private array $messageTimes = [];

    /** @var callable(string):void */
    private $writer;

    public function __construct(public readonly int $id, callable $writer)
    {
        $this->writer = $writer;
    }

    public function setIdentity(int $userId, string $username, int $roomId): void
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->roomId = $roomId;
    }

    public function sendRaw(string $bytes): void
    {
        ($this->writer)($bytes);
    }

    public function sendFrame(string $payload, int $opcode = Frame::TEXT): void
    {
        $this->sendRaw(Frame::encode($payload, $opcode));
    }

    /** @param array<string,mixed> $data */
    public function sendJson(array $data): void
    {
        $this->sendFrame(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function close(int $code = 1000): void
    {
        if (!$this->open) {
            return;
        }
        // Close-Frame mit Statuscode senden.
        $this->sendFrame(pack('n', $code), Frame::CLOSE);
        $this->open = false;
    }

    /**
     * Token-Bucket-artiges Rate-Limit: erlaubt hoechstens $max Nachrichten
     * innerhalb von $windowSeconds Sekunden.
     */
    public function allowMessage(int $max, float $windowSeconds): bool
    {
        $now = microtime(true);
        $this->messageTimes = array_values(array_filter(
            $this->messageTimes,
            static fn (float $t): bool => ($now - $t) < $windowSeconds
        ));

        if (count($this->messageTimes) >= $max) {
            return false;
        }

        $this->messageTimes[] = $now;

        return true;
    }
}
