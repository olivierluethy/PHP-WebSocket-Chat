<?php

declare(strict_types=1);

/**
 * Bootstrap: Autoloader und gemeinsame Grundeinstellungen fuer Web- und
 * WebSocket-Prozess. Bewusst dependency-frei (kein Composer).
 */

// Fehler nie an den Client ausgeben (Info-Leak vermeiden); nur ins Log.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$rootDir = \dirname(__DIR__);

// Einfacher PSR-4-artiger Autoloader fuer den Namespace "App\" -> src/.
spl_autoload_register(static function (string $class) use ($rootDir): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $rootDir . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

return $rootDir;
