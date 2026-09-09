<?php

declare(strict_types=1);

namespace App\Core;

/** CSRF-Schutz per Synchronizer-Token, gebunden an die Session. */
final class Csrf
{
    private const KEY = '_csrf';

    /** Liefert (und erzeugt bei Bedarf) das CSRF-Token der aktuellen Session. */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    /** Prueft ein uebermitteltes Token gegen das der Session (zeitkonstant). */
    public static function check(?string $token): bool
    {
        $stored = $_SESSION[self::KEY] ?? null;

        return is_string($token)
            && is_string($stored)
            && $stored !== ''
            && hash_equals($stored, $token);
    }

    /** Verstecktes Formularfeld mit dem CSRF-Token. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
