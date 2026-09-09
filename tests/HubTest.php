<?php

declare(strict_types=1);

use App\Database\Connection as Db;
use App\Database\Migrator;
use App\Repository\MessageRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\WebSocket\Connection;
use App\WebSocket\Frame;
use App\WebSocket\Hub;

/**
 * Erzeugt eine Verbindung, die alle gesendeten Bytes in $sink ablegt.
 * @param array<int,string> $sink
 */
function makeConn(int $id, string $username, int $roomId, array &$sink): Connection
{
    $conn = new Connection($id, function (string $bytes) use (&$sink): void {
        $sink[] = $bytes;
    });
    $conn->setIdentity($id, $username, $roomId);

    return $conn;
}

/**
 * Dekodiert alle in $sink gesammelten Text-Frames zu JSON-Arrays.
 * @param array<int,string> $sink
 * @return list<array<string,mixed>>
 */
function decodeSink(array $sink): array
{
    $out = [];
    foreach ($sink as $bytes) {
        foreach (Frame::decode($bytes)['frames'] as $frame) {
            if ($frame['opcode'] === Frame::TEXT) {
                $out[] = json_decode($frame['payload'], true);
            }
        }
    }

    return $out;
}

/** @param list<array<string,mixed>> $messages */
function typesOf(array $messages): array
{
    return array_map(static fn (array $m): string => (string) $m['type'], $messages);
}

/** @return array{0:Hub,1:MessageRepository,2:int} */
function hubWithRoom(): array
{
    $pdo = Db::memory();
    Migrator::migrate($pdo);
    $users = new UserRepository($pdo);
    $rooms = new RoomRepository($pdo);
    $messages = new MessageRepository($pdo);

    $uid = $users->create('alice', 'H');
    $roomId = $rooms->create('General', $uid);

    return [new Hub($messages), $messages, $roomId];
}

test('Hub: join sendet Verlauf und Presence', function (): void {
    [$hub, $messages, $roomId] = hubWithRoom();
    $messages->add($roomId, 1, 'alice', 'alte Nachricht 1');
    $messages->add($roomId, 1, 'alice', 'alte Nachricht 2');

    $sink = [];
    $conn = makeConn(1, 'alice', $roomId, $sink);
    $hub->join($conn);

    $decoded = decodeSink($sink);
    $types = typesOf($decoded);
    assertTrue(in_array('history', $types, true));
    assertTrue(in_array('presence', $types, true));

    $history = array_values(array_filter($decoded, fn ($m) => $m['type'] === 'history'))[0];
    assertEquals(2, count($history['messages']));

    $presence = array_values(array_filter($decoded, fn ($m) => $m['type'] === 'presence'))[0];
    assertEquals(['alice'], $presence['users']);
});

test('Hub: Nachricht wird persistiert und an den Raum verteilt', function (): void {
    [$hub, $messages, $roomId] = hubWithRoom();

    $sinkA = [];
    $sinkB = [];
    $a = makeConn(1, 'alice', $roomId, $sinkA);
    $b = makeConn(2, 'bob', $roomId, $sinkB);
    $hub->join($a);
    $hub->join($b);

    $sinkA = [];
    $sinkB = [];
    $hub->onMessage($a, json_encode(['type' => 'message', 'body' => 'Hallo Welt']));

    // Beide erhalten die Nachricht
    foreach ([$sinkA, $sinkB] as $sink) {
        $msgs = array_values(array_filter(decodeSink($sink), fn ($m) => $m['type'] === 'message'));
        assertEquals(1, count($msgs));
        assertEquals('Hallo Welt', $msgs[0]['body']);
        assertEquals('alice', $msgs[0]['username']);
    }

    // Persistiert
    $recent = $messages->recent($roomId, 50);
    assertEquals('Hallo Welt', $recent[count($recent) - 1]['body']);
});

test('Hub: leere und zu lange Nachrichten werden abgewiesen', function (): void {
    [$hub, $messages, $roomId] = hubWithRoom();
    $sink = [];
    $conn = makeConn(1, 'alice', $roomId, $sink);
    $hub->join($conn);

    $hub->onMessage($conn, json_encode(['type' => 'message', 'body' => '   ']));
    assertEquals(0, count($messages->recent($roomId, 50)));

    $sink = [];
    $hub->onMessage($conn, json_encode(['type' => 'message', 'body' => str_repeat('x', 2001)]));
    assertEquals(0, count($messages->recent($roomId, 50)));
    $errors = array_filter(decodeSink($sink), fn ($m) => $m['type'] === 'error');
    assertTrue(count($errors) >= 1);
});

test('Hub: Rate-Limit greift nach zu vielen Nachrichten', function (): void {
    [$hub, $messages, $roomId] = hubWithRoom();
    $sink = [];
    $conn = makeConn(1, 'alice', $roomId, $sink);
    $hub->join($conn);

    for ($i = 0; $i < 15; $i++) {
        $hub->onMessage($conn, json_encode(['type' => 'message', 'body' => "m{$i}"]));
    }

    // Hoechstens 10 persistiert
    assertEquals(10, count($messages->recent($roomId, 50)));
});

test('Hub: leave aktualisiert Presence', function (): void {
    [$hub, $messages, $roomId] = hubWithRoom();
    $sinkA = [];
    $sinkB = [];
    $a = makeConn(1, 'alice', $roomId, $sinkA);
    $b = makeConn(2, 'bob', $roomId, $sinkB);
    $hub->join($a);
    $hub->join($b);

    $sinkB = [];
    $hub->leave($a);

    $presences = array_values(array_filter(decodeSink($sinkB), fn ($m) => $m['type'] === 'presence'));
    $last = $presences[count($presences) - 1];
    assertEquals(['bob'], $last['users']);
    assertEquals(1, $last['count']);
});
