<?php

declare(strict_types=1);

namespace App\Core;

/** Kapselt die eingehende HTTP-Anfrage. */
final class Request
{
    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        // Trailing Slash normalisieren (ausser Root).
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return new self($method, $path, $_GET, $_POST);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function post(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
