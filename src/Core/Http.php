<?php

declare(strict_types=1);

namespace App\Core;

/** Kleine Helfer fuer HTTP-Antworten. */
final class Http
{
    /** Sendet einen Redirect und beendet die Ausfuehrung. */
    public static function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . $path, true, $status);
        exit;
    }

    /** Setzt einen HTTP-Statuscode. */
    public static function status(int $code): void
    {
        http_response_code($code);
    }
}
