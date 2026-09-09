<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\Auth\WsToken;
use App\Core\Config;

/**
 * Fuehrt den RFC-6455-Handshake durch: prueft den Upgrade, den Origin gegen
 * eine Allowlist (Schutz vor Cross-Site WebSocket Hijacking) und verifiziert
 * das signierte WsToken. Die Identitaet stammt ausschliesslich aus dem Token.
 */
final class Handshake
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /** Berechnet den Sec-WebSocket-Accept-Wert zum uebergebenen Key. */
    public static function computeAccept(string $key): string
    {
        return base64_encode(sha1($key . self::GUID, true));
    }

    /**
     * Verarbeitet die rohe HTTP-Upgrade-Anfrage.
     *
     * @param list<string>|null $allowedOrigins Default: Config::allowedOrigins()
     * @return array{ok:bool,response:string,userId:int|null,username:string|null,roomId:int|null,error:string|null}
     */
    public static function handle(string $request, ?array $allowedOrigins = null): array
    {
        $allowedOrigins ??= Config::allowedOrigins();

        $lines = preg_split('/\r\n/', $request) ?: [];
        $requestLine = array_shift($lines) ?? '';

        // Request-Ziel (Pfad + Query) aus der ersten Zeile.
        $target = '/';
        if (preg_match('#^GET\s+(\S+)\s+HTTP/1\.[01]$#', $requestLine, $m) === 1) {
            $target = $m[1];
        } else {
            return self::reject('Ungueltige Request-Line', 400);
        }

        $headers = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $headers[$name] = trim(substr($line, $pos + 1));
        }

        // Upgrade-Header pruefen.
        if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) !== 'websocket') {
            return self::reject('Kein WebSocket-Upgrade', 400);
        }
        if (!isset($headers['sec-websocket-key']) || $headers['sec-websocket-key'] === '') {
            return self::reject('Fehlender Sec-WebSocket-Key', 400);
        }

        // Origin gegen Allowlist pruefen.
        $origin = $headers['origin'] ?? '';
        if (!in_array($origin, $allowedOrigins, true)) {
            return self::reject('Origin nicht erlaubt', 403);
        }

        // Query auswerten: token + room.
        $query = parse_url($target, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);

        $token = isset($params['token']) && is_string($params['token']) ? $params['token'] : '';
        $identity = WsToken::verify($token);
        if ($identity === null) {
            return self::reject('Ungueltiges oder abgelaufenes Token', 401);
        }

        $roomId = isset($params['room']) ? (int) $params['room'] : 0;
        if ($roomId <= 0) {
            return self::reject('Kein gueltiger Raum', 400);
        }

        $accept = self::computeAccept($headers['sec-websocket-key']);
        $response = "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

        return [
            'ok'       => true,
            'response' => $response,
            'userId'   => $identity['userId'],
            'username' => $identity['username'],
            'roomId'   => $roomId,
            'error'    => null,
        ];
    }

    /**
     * @return array{ok:bool,response:string,userId:null,username:null,roomId:null,error:string}
     */
    private static function reject(string $reason, int $status): array
    {
        $text = match ($status) {
            401 => 'Unauthorized',
            403 => 'Forbidden',
            default => 'Bad Request',
        };

        return [
            'ok'       => false,
            'response' => "HTTP/1.1 {$status} {$text}\r\nConnection: close\r\n\r\n",
            'userId'   => null,
            'username' => null,
            'roomId'   => null,
            'error'    => $reason,
        ];
    }
}
