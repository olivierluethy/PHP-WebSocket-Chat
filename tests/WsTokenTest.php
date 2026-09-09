<?php

declare(strict_types=1);

use App\Auth\WsToken;

// Stabiles Secret fuer die Tests setzen (bevor Config es cached).
putenv('APP_SECRET=test-secret-fuer-unit-tests');

test('WsToken: gueltiges Token verifiziert und liefert Identitaet', function (): void {
    $token = WsToken::issue(42, 'alice', 60);
    $result = WsToken::verify($token);

    assertNotNull($result);
    assertEquals(42, $result['userId']);
    assertEquals('alice', $result['username']);
});

test('WsToken: manipuliertes Token wird abgelehnt', function (): void {
    $token = WsToken::issue(42, 'alice', 60);
    $tampered = $token . 'x';
    assertNull(WsToken::verify($tampered));

    // Payload veraendern, alte Signatur behalten
    [$enc, $sig] = explode('.', $token);
    $forged = rtrim(strtr(base64_encode('{"uid":999,"name":"mallory","exp":9999999999}'), '+/', '-_'), '=') . '.' . $sig;
    assertNull(WsToken::verify($forged));
});

test('WsToken: abgelaufenes Token wird abgelehnt', function (): void {
    $token = WsToken::issue(42, 'alice', -1); // sofort abgelaufen
    assertNull(WsToken::verify($token));
});

test('WsToken: Muell-Eingaben werden abgelehnt', function (): void {
    assertNull(WsToken::verify(''));
    assertNull(WsToken::verify('kein-punkt'));
    assertNull(WsToken::verify('a.b.c'));
});
