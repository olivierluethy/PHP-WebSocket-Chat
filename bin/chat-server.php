<?php

declare(strict_types=1);

/**
 * Einstiegspunkt des WebSocket-Servers.
 * Aufruf: php bin/chat-server.php
 */

use App\Core\Config;
use App\Database\Connection;
use App\Database\Migrator;
use App\Repository\MessageRepository;
use App\WebSocket\Hub;
use App\WebSocket\Server;

require \dirname(__DIR__) . '/config/config.php';

// Im CLI-Betrieb Fehler sichtbar machen (aber ohne HTTP-Client-Leak-Risiko).
ini_set('display_errors', '1');

$pdo = Connection::pdo();
Migrator::migrate($pdo);

$hub = new Hub(new MessageRepository($pdo));

$host = Config::wsHost();
$port = Config::wsPort();

echo "WebSocket-Server laeuft auf ws://{$host}:{$port}\n";
echo "Erlaubte Origins: " . implode(', ', Config::allowedOrigins()) . "\n";
echo "Zum Beenden: Strg+C\n";

(new Server($host, $port, $hub))->run();
