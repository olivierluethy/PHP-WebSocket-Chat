<?php

declare(strict_types=1);

use App\Auth\WsToken;
use App\WebSocket\Handshake;

// Deterministisches Secret (falls WsTokenTest nicht zuerst laedt).
putenv('APP_SECRET=test-secret-fuer-unit-tests');

test('Handshake: Accept-Key entspricht dem RFC-6455-Beispiel', function (): void {
    // Beispiel aus RFC 6455, Abschnitt 1.3
    $accept = Handshake::computeAccept('dGhlIHNhbXBsZSBub25jZQ==');
    assertEquals('s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', $accept);
});

function buildRequest(string $origin, string $token, int $room): string
{
    return "GET /?token={$token}&room={$room} HTTP/1.1\r\n"
        . "Host: 127.0.0.1:8080\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
        . "Sec-WebSocket-Version: 13\r\n"
        . "Origin: {$origin}\r\n\r\n";
}

test('Handshake: gueltige Anfrage wird akzeptiert', function (): void {
    $token = WsToken::issue(7, 'alice', 60);
    $req = buildRequest('http://localhost:8000', $token, 3);

    $res = Handshake::handle($req, ['http://localhost:8000']);
    assertTrue($res['ok']);
    assertEquals(7, $res['userId']);
    assertEquals('alice', $res['username']);
    assertEquals(3, $res['roomId']);
    assertTrue(str_contains($res['response'], '101 Switching Protocols'));
});

test('Handshake: fremder Origin wird abgelehnt', function (): void {
    $token = WsToken::issue(7, 'alice', 60);
    $req = buildRequest('http://evil.example', $token, 3);

    $res = Handshake::handle($req, ['http://localhost:8000']);
    assertFalse($res['ok']);
    assertTrue(str_contains($res['response'], '403'));
});

test('Handshake: ungueltiges Token wird abgelehnt', function (): void {
    $req = buildRequest('http://localhost:8000', 'kaputt.token', 3);
    $res = Handshake::handle($req, ['http://localhost:8000']);
    assertFalse($res['ok']);
    assertTrue(str_contains($res['response'], '401'));
});

test('Handshake: fehlender Raum wird abgelehnt', function (): void {
    $token = WsToken::issue(7, 'alice', 60);
    $req = buildRequest('http://localhost:8000', $token, 0);
    $res = Handshake::handle($req, ['http://localhost:8000']);
    assertFalse($res['ok']);
    assertTrue(str_contains($res['response'], '400'));
});
