<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Migrator;

test('Migrator legt alle Tabellen an', function (): void {
    $pdo = Connection::memory();
    Migrator::migrate($pdo);

    $tables = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach (['messages', 'rooms', 'users'] as $expected) {
        assertTrue(in_array($expected, $tables, true), "Tabelle {$expected} fehlt");
    }
});

test('Migrator ist idempotent (zweiter Lauf wirft nicht)', function (): void {
    $pdo = Connection::memory();
    Migrator::migrate($pdo);
    Migrator::migrate($pdo); // darf nicht werfen
    assertTrue(true);
});

test('Fremdschluessel sind aktiviert', function (): void {
    $pdo = Connection::memory();
    $on = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
    assertEquals(1, $on);
});
