<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migrator;
use App\Repository\MessageRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;

/** @return PDO frische migrierte In-Memory-DB */
function freshDb(): PDO
{
    $pdo = Connection::memory();
    Migrator::migrate($pdo);

    return $pdo;
}

test('UserRepository: create + findByUsername + findById', function (): void {
    $repo = new UserRepository(freshDb());
    $id = $repo->create('alice', 'HASH');
    assertTrue($id > 0);

    $byName = $repo->findByUsername('alice');
    assertNotNull($byName);
    assertEquals('alice', $byName['username']);
    assertEquals('HASH', $byName['password_hash']);

    $byId = $repo->findById($id);
    assertNotNull($byId);
    assertEquals($id, $byId['id']);

    assertNull($repo->findByUsername('bob'));
    assertTrue($repo->existsByUsername('alice'));
    assertFalse($repo->existsByUsername('bob'));
});

test('UserRepository: doppelter Username verletzt UNIQUE', function (): void {
    $repo = new UserRepository(freshDb());
    $repo->create('alice', 'H');
    assertThrows(fn () => $repo->create('alice', 'H2'), PDOException::class);
});

test('RoomRepository: create, all, findByName, findById', function (): void {
    $pdo = freshDb();
    $users = new UserRepository($pdo);
    $uid = $users->create('alice', 'H');
    $rooms = new RoomRepository($pdo);

    $r1 = $rooms->create('Zebra', $uid);
    $r2 = $rooms->create('Alpha', $uid);

    $all = $rooms->all();
    assertEquals(2, count($all));
    // Sortierung alphabetisch (NOCASE): Alpha vor Zebra
    assertEquals('Alpha', $all[0]['name']);
    assertEquals('Zebra', $all[1]['name']);

    $byName = $rooms->findByName('Alpha');
    assertNotNull($byName);
    assertEquals($r2, $byName['id']);

    $byId = $rooms->findById($r1);
    assertNotNull($byId);
    assertEquals('Zebra', $byId['name']);

    assertNull($rooms->findByName('Nix'));
});

test('MessageRepository: add + recent (Reihenfolge und Limit)', function (): void {
    $pdo = freshDb();
    $users = new UserRepository($pdo);
    $rooms = new RoomRepository($pdo);
    $messages = new MessageRepository($pdo);

    $uid = $users->create('alice', 'H');
    $rid = $rooms->create('General', $uid);

    for ($i = 1; $i <= 5; $i++) {
        $messages->add($rid, $uid, 'alice', "Nachricht {$i}");
    }

    $recent = $messages->recent($rid, 3);
    assertEquals(3, count($recent));
    // Chronologisch aufsteigend: die letzten drei sind 3,4,5
    assertEquals('Nachricht 3', $recent[0]['body']);
    assertEquals('Nachricht 5', $recent[2]['body']);
    assertEquals('alice', $recent[0]['username']);

    // Raum ohne Nachrichten -> leer
    $rid2 = $rooms->create('Leer', $uid);
    assertEquals(0, count($messages->recent($rid2, 50)));
});
