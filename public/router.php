<?php

declare(strict_types=1);

/**
 * Router fuer den eingebauten PHP-Webserver (php -S). Bestehende statische
 * Dateien (CSS/JS) werden direkt ausgeliefert, alles andere geht an den
 * Front-Controller. So funktionieren die sauberen URLs (/login, /room/1 …).
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

// Nur echte Dateien unterhalb von public/ direkt ausliefern (keine .php-Quellen).
if ($path !== '/' && is_file($file) && !str_ends_with($path, '.php')) {
    return false;
}

require __DIR__ . '/index.php';
