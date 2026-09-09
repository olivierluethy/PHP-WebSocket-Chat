<?php

declare(strict_types=1);

/**
 * Minimaler, dependency-freier Test-Runner.
 *
 * Jede *Test.php-Datei registriert Faelle ueber test('name', fn). Assertions
 * werfen bei Fehlschlag eine Exception. Aufruf: `php tests/run.php`.
 */

require __DIR__ . '/../config/config.php';

// Fehler in Tests sichtbar machen (im Gegensatz zum Produktions-Bootstrap).
ini_set('display_errors', '1');
error_reporting(E_ALL);

/** @var list<array{0:string,1:callable}> */
$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

final class AssertionFailed extends \RuntimeException
{
}

function assertTrue(mixed $cond, string $msg = ''): void
{
    if ($cond !== true) {
        throw new AssertionFailed($msg !== '' ? $msg : 'erwartet: true, erhalten: ' . var_export($cond, true));
    }
}

function assertFalse(mixed $cond, string $msg = ''): void
{
    if ($cond !== false) {
        throw new AssertionFailed($msg !== '' ? $msg : 'erwartet: false, erhalten: ' . var_export($cond, true));
    }
}

function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(
            ($msg !== '' ? $msg . ' — ' : '') .
            'erwartet: ' . var_export($expected, true) . ', erhalten: ' . var_export($actual, true)
        );
    }
}

function assertNull(mixed $actual, string $msg = ''): void
{
    if ($actual !== null) {
        throw new AssertionFailed($msg !== '' ? $msg : 'erwartet: null, erhalten: ' . var_export($actual, true));
    }
}

function assertNotNull(mixed $actual, string $msg = ''): void
{
    if ($actual === null) {
        throw new AssertionFailed($msg !== '' ? $msg : 'erwartet: nicht null');
    }
}

/** Prueft, dass $fn eine Throwable wirft (optional bestimmter Klasse). */
function assertThrows(callable $fn, ?string $class = null, string $msg = ''): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        if ($class !== null && !($e instanceof $class)) {
            throw new AssertionFailed(($msg !== '' ? $msg . ' — ' : '') . 'falscher Exception-Typ: ' . $e::class);
        }
        return;
    }
    throw new AssertionFailed($msg !== '' ? $msg : 'erwartete eine Exception, es wurde keine geworfen');
}

// Alle Testdateien laden (registrieren ihre Faelle).
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$failures = 0;
$count = 0;
foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    $count++;
    try {
        $fn();
        echo "  ok   {$name}\n";
    } catch (\Throwable $e) {
        $failures++;
        echo "  FAIL {$name}\n       {$e->getMessage()}\n";
    }
}

echo "\n" . ($failures === 0 ? 'OK' : 'FEHLER') . ": {$count} Tests, {$failures} fehlgeschlagen\n";
exit($failures === 0 ? 0 : 1);
