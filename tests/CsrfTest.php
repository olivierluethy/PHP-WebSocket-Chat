<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\View;

test('Csrf: token ist stabil, check unterscheidet gueltig/ungueltig', function (): void {
    $_SESSION = [];

    $token = Csrf::token();
    assertTrue(strlen($token) >= 32);
    // Zweiter Aufruf liefert dasselbe Token
    assertEquals($token, Csrf::token());

    assertTrue(Csrf::check($token));
    assertFalse(Csrf::check('falsch'));
    assertFalse(Csrf::check(null));
    assertFalse(Csrf::check(''));
});

test('Csrf: ohne Session-Token schlaegt check fehl', function (): void {
    $_SESSION = [];
    assertFalse(Csrf::check('irgendwas'));
});

test('View::e escaped gefaehrliche Zeichen', function (): void {
    $out = View::e('<script>alert("x")</script>');
    assertFalse(str_contains($out, '<script>'));
    assertTrue(str_contains($out, '&lt;script&gt;'));
    // Anfuehrungszeichen ebenfalls escaped
    assertTrue(str_contains($out, '&quot;'));
});

test('globaler e()-Helfer escaped ebenfalls', function (): void {
    assertEquals('&lt;b&gt;', e('<b>'));
});
