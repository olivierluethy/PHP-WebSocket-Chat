<?php

declare(strict_types=1);

namespace App\Core;

/** Duennes, sicheres Session-Handling. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Sichere Cookie-Parameter: nicht per JS lesbar, kein Cross-Site-Versand.
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true, // bei HTTPS/wss in Produktion aktivieren
        ]);
        session_name('chatsid');
        session_start();
    }

    /** Session-ID nach Login erneuern (Schutz vor Session-Fixation). */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    public static function username(): ?string
    {
        $name = $_SESSION['username'] ?? null;

        return $name === null ? null : (string) $name;
    }

    public static function isAuthenticated(): bool
    {
        return self::userId() !== null;
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
