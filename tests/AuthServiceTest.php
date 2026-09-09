<?php

declare(strict_types=1);

use App\Auth\AuthService;
use App\Database\Connection;
use App\Database\Migrator;

function authService(): AuthService
{
    $pdo = Connection::memory();
    Migrator::migrate($pdo);

    return new AuthService($pdo);
}

test('AuthService: erfolgreiche Registrierung', function (): void {
    $svc = authService();
    $res = $svc->register('alice', 'geheim1234', 'geheim1234');

    assertTrue($res['ok']);
    assertEquals([], $res['errors']);
    assertNotNull($res['userId']);
});

test('AuthService: ungueltiger Benutzername wird abgelehnt', function (): void {
    $svc = authService();
    $res = $svc->register('a b!', 'geheim1234', 'geheim1234');
    assertFalse($res['ok']);
    assertTrue(count($res['errors']) >= 1);
});

test('AuthService: zu kurzes Passwort wird abgelehnt', function (): void {
    $svc = authService();
    $res = $svc->register('alice', 'kurz', 'kurz');
    assertFalse($res['ok']);
});

test('AuthService: nicht uebereinstimmende Passwoerter', function (): void {
    $svc = authService();
    $res = $svc->register('alice', 'geheim1234', 'anders9999');
    assertFalse($res['ok']);
});

test('AuthService: doppelter Benutzername', function (): void {
    $svc = authService();
    $svc->register('alice', 'geheim1234', 'geheim1234');
    $res = $svc->register('alice', 'geheim1234', 'geheim1234');
    assertFalse($res['ok']);
});

test('AuthService: Login erfolgreich und fehlerhaft', function (): void {
    $svc = authService();
    $svc->register('alice', 'geheim1234', 'geheim1234');

    $ok = $svc->login('alice', 'geheim1234');
    assertNotNull($ok);
    assertEquals('alice', $ok['username']);

    assertNull($svc->login('alice', 'falsch'));
    assertNull($svc->login('bob', 'geheim1234'));
});
