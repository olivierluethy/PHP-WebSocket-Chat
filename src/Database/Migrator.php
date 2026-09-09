<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Legt das Datenbankschema idempotent an. Wird beim Start von Web- und
 * WebSocket-Prozess aufgerufen, sodass die DB ohne manuelle Schritte bereitsteht.
 */
final class Migrator
{
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rooms (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL UNIQUE,
                created_by INTEGER,
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS messages (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id           INTEGER NOT NULL,
                user_id           INTEGER,
                username_snapshot TEXT NOT NULL,
                body              TEXT NOT NULL,
                created_at        TEXT NOT NULL DEFAULT (datetime(\'now\')),
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )'
        );

        // Schneller Zugriff auf den Verlauf eines Raums.
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_room ON messages(room_id, id)');
    }
}
