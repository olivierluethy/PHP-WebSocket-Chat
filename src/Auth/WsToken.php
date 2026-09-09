<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Config;

/**
 * Kurzlebiges, HMAC-signiertes Token, das die authentifizierte Web-Session an
 * eine WebSocket-Verbindung bindet. Der WS-Server vertraut ausschliesslich dem
 * Inhalt dieses Tokens (nie clientseitig gesendeten Identitaetsfeldern), womit
 * Identitaets-Spoofing verhindert wird.
 *
 * Format: base64url(JSON-Payload) "." base64url(HMAC-SHA256).
 */
final class WsToken
{
    /** Erzeugt ein Token fuer den Nutzer mit Gueltigkeit von $ttl Sekunden. */
    public static function issue(int $userId, string $username, int $ttl = 60): string
    {
        $payload = [
            'uid'  => $userId,
            'name' => $username,
            'exp'  => time() + $ttl,
        ];

        $encoded = self::b64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = self::sign($encoded);

        return $encoded . '.' . $signature;
    }

    /**
     * Prueft Signatur und Ablauf. Gibt bei Erfolg die Identitaet zurueck, sonst null.
     *
     * @return array{userId:int,username:string}|null
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        $expected = self::sign($encoded);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $json = self::b64UrlDecode($encoded);
        if ($json === null) {
            return null;
        }

        try {
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($payload)
            || !isset($payload['uid'], $payload['name'], $payload['exp'])
            || !is_int($payload['exp'])
        ) {
            return null;
        }

        if ($payload['exp'] < time()) {
            return null;
        }

        return [
            'userId'   => (int) $payload['uid'],
            'username' => (string) $payload['name'],
        ];
    }

    private static function sign(string $data): string
    {
        return self::b64UrlEncode(
            hash_hmac('sha256', $data, Config::appSecret(), true)
        );
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): ?string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
