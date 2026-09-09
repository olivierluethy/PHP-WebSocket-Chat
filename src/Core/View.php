<?php

declare(strict_types=1);

namespace App\Core;

/** Rendert PHP-Templates aus views/. Ausgaben werden mit e() escaped. */
final class View
{
    /** HTML-Escaping (Schutz vor XSS in Templates). */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Rendert ein Template und gibt das Ergebnis als String zurueck.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        $file = \dirname(__DIR__, 2) . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View nicht gefunden: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    /**
     * Rendert ein Inhalts-Template und bettet es in das Layout ein.
     *
     * @param array<string,mixed> $data
     */
    public static function page(string $template, array $data = [], string $title = 'Chat'): string
    {
        $content = self::render($template, $data);

        return self::render('layout', array_merge($data, [
            'content' => $content,
            'title'   => $title,
        ]));
    }
}
