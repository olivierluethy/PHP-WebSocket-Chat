<?php

declare(strict_types=1);

namespace App\WebSocket;

/**
 * De-/Encoding von WebSocket-Frames nach RFC 6455. Bewusst dependency-frei.
 */
final class Frame
{
    public const CONTINUATION = 0x0;
    public const TEXT         = 0x1;
    public const BINARY       = 0x2;
    public const CLOSE        = 0x8;
    public const PING         = 0x9;
    public const PONG         = 0xA;

    /** Maximale erlaubte Payload-Groesse eines einzelnen Frames (1 MiB). */
    public const MAX_PAYLOAD = 1048576;

    /**
     * Zerlegt einen Byte-Puffer in vollstaendige Frames. Unvollstaendige
     * Restbytes werden unter 'rest' zurueckgegeben und beim naechsten Aufruf
     * erneut vorangestellt.
     *
     * @return array{frames:list<array{fin:bool,opcode:int,payload:string}>,rest:string}
     */
    public static function decode(string $buffer, int $maxPayload = self::MAX_PAYLOAD): array
    {
        $frames = [];
        $offset = 0;
        $length = strlen($buffer);

        while (true) {
            if ($length - $offset < 2) {
                break;
            }

            $b0 = ord($buffer[$offset]);
            $b1 = ord($buffer[$offset + 1]);

            $fin = ($b0 & 0x80) !== 0;
            $opcode = $b0 & 0x0F;
            $masked = ($b1 & 0x80) !== 0;
            $payloadLen = $b1 & 0x7F;
            $headerLen = 2;

            if ($payloadLen === 126) {
                if ($length - $offset < 4) {
                    break;
                }
                /** @var array{1:int} $u */
                $u = unpack('n', substr($buffer, $offset + 2, 2));
                $payloadLen = $u[1];
                $headerLen = 4;
            } elseif ($payloadLen === 127) {
                if ($length - $offset < 10) {
                    break;
                }
                /** @var array{1:int} $u */
                $u = unpack('J', substr($buffer, $offset + 2, 8));
                $payloadLen = $u[1];
                $headerLen = 10;
            }

            if ($payloadLen < 0 || $payloadLen > $maxPayload) {
                throw new FrameException('Frame-Payload zu gross oder ungueltig');
            }

            $maskLen = $masked ? 4 : 0;
            $frameLen = $headerLen + $maskLen + $payloadLen;

            if ($length - $offset < $frameLen) {
                break; // Frame noch nicht vollstaendig empfangen
            }

            $payload = substr($buffer, $offset + $headerLen + $maskLen, $payloadLen);
            if ($masked) {
                $maskKey = substr($buffer, $offset + $headerLen, 4);
                $payload = self::applyMask($payload, $maskKey);
            }

            $frames[] = ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
            $offset += $frameLen;
        }

        return ['frames' => $frames, 'rest' => substr($buffer, $offset)];
    }

    /** Kodiert eine Server-Nachricht (FIN gesetzt, ungemaskt). */
    public static function encode(string $payload, int $opcode = self::TEXT): string
    {
        $length = strlen($payload);
        $header = chr(0x80 | ($opcode & 0x0F));

        if ($length < 126) {
            $header .= chr($length);
        } elseif ($length < 65536) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $header .= chr(127) . pack('J', $length);
        }

        return $header . $payload;
    }

    private static function applyMask(string $payload, string $key): string
    {
        $out = '';
        $len = strlen($payload);
        for ($i = 0; $i < $len; $i++) {
            $out .= $payload[$i] ^ $key[$i % 4];
        }

        return $out;
    }

    /** Baut zu Testzwecken einen maskierten Client-Frame. */
    public static function encodeMasked(string $payload, int $opcode = self::TEXT): string
    {
        $length = strlen($payload);
        $header = chr(0x80 | ($opcode & 0x0F));
        $maskBit = 0x80;

        if ($length < 126) {
            $header .= chr($maskBit | $length);
        } elseif ($length < 65536) {
            $header .= chr($maskBit | 126) . pack('n', $length);
        } else {
            $header .= chr($maskBit | 127) . pack('J', $length);
        }

        $key = random_bytes(4);

        return $header . $key . self::applyMask($payload, $key);
    }
}
