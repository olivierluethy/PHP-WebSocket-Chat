<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Config;
use PDO;

/**
 * Erzeugt PDO-Verbindungen zur SQLite-Datenbank. Ein einziges DB-Layer fuer
 * die gesamte Anwendung (kein mysqli mehr).
 */
final class Connection
{
    public static function pdo(?string $path = null): PDO
    {
        $path ??= Config::dbPath();

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Fremdschluessel in SQLite explizit aktivieren.
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /** In-Memory-Datenbank fuer Tests. */
    public static function memory(): PDO
    {
        return self::pdo(':memory:');
    }
}
