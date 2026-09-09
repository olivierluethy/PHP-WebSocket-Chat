<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Zentrale Konfiguration. Liest Werte aus Umgebungsvariablen mit sicheren
 * Defaults. So laeuft die App ohne jede Vorkonfiguration lokal an, kann aber
 * fuer den Betrieb per Env angepasst werden.
 */
final class Config
{
    /** In-Memory-Cache fuer das App-Secret, damit es pro Prozess stabil bleibt. */
    private static ?string $secret = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = getenv($key);

        return $value === false ? $default : (int) $value;
    }

    /** Absoluter Pfad zur SQLite-Datei. */
    public static function dbPath(): string
    {
        $path = self::get('DB_PATH');
        if ($path !== null && $path !== '') {
            return $path;
        }

        return self::dataDir() . '/chat.sqlite';
    }

    public static function dataDir(): string
    {
        $dir = \dirname(__DIR__, 2) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        return $dir;
    }

    public static function webHost(): string
    {
        return self::get('WEB_HOST', '127.0.0.1');
    }

    public static function webPort(): int
    {
        return self::int('WEB_PORT', 8000);
    }

    public static function wsHost(): string
    {
        return self::get('WS_HOST', '127.0.0.1');
    }

    public static function wsPort(): int
    {
        return self::int('WS_PORT', 8080);
    }

    /**
     * WebSocket-URL, die der Browser verwendet. Standard nutzt denselben Host
     * wie die Web-App, damit es im lokalen Betrieb ohne Anpassung funktioniert.
     */
    public static function wsPublicUrl(): string
    {
        $url = self::get('WS_PUBLIC_URL');
        if ($url !== null && $url !== '') {
            return $url;
        }

        return 'ws://' . self::webHost() . ':' . self::wsPort();
    }

    /**
     * Erlaubte Origins fuer den WebSocket-Handshake (Schutz vor Cross-Site
     * WebSocket Hijacking). Komma-separiert per Env, sonst lokale Defaults.
     *
     * @return list<string>
     */
    public static function allowedOrigins(): array
    {
        $raw = self::get('ALLOWED_ORIGINS');
        if ($raw !== null && $raw !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        $host = self::webHost();
        $port = self::webPort();

        return [
            "http://{$host}:{$port}",
            "http://localhost:{$port}",
        ];
    }

    /**
     * Geheimnis zum Signieren der WebSocket-Tokens. Web- und WS-Prozess muessen
     * dasselbe Secret verwenden. Reihenfolge: Env-Variable, sonst eine persistente
     * Datei in data/ (wird beim ersten Aufruf sicher zufaellig erzeugt).
     */
    public static function appSecret(): string
    {
        if (self::$secret !== null) {
            return self::$secret;
        }

        $env = self::get('APP_SECRET');
        if ($env !== null && $env !== '') {
            return self::$secret = $env;
        }

        $file = self::dataDir() . '/app_secret.key';
        if (is_file($file)) {
            $content = trim((string) file_get_contents($file));
            if ($content !== '') {
                return self::$secret = $content;
            }
        }

        $generated = bin2hex(random_bytes(32));
        // Restriktive Rechte, Secret gehoert nicht ins Repo (.gitignore).
        file_put_contents($file, $generated, LOCK_EX);
        @chmod($file, 0600);

        return self::$secret = $generated;
    }
}
