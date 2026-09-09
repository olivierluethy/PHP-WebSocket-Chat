<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Einfacher Router. Registriert nur explizit definierte Routen (keine
 * Ableitung von Controllernamen aus der URL wie im alten Projekt).
 * Unterstuetzt Platzhalter der Form {name}.
 */
final class Router
{
    /** @var array<string,list<array{regex:string,params:list<string>,handler:callable}>> */
    private array $routes = [];

    /** @var callable|null */
    private $notFound = null;

    public function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);

        $params = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '([^/]+)';
            },
            $path
        );

        $this->routes[$method][] = [
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function setNotFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    /** Sucht eine passende Route und ruft deren Handler mit (Request, ...params) auf. */
    public function dispatch(Request $request): mixed
    {
        $candidates = $this->routes[$request->method()] ?? [];

        foreach ($candidates as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) === 1) {
                array_shift($matches); // vollstaendigen Treffer entfernen
                $args = array_map('urldecode', $matches);

                return ($route['handler'])($request, ...$args);
            }
        }

        if ($this->notFound !== null) {
            return ($this->notFound)($request);
        }

        http_response_code(404);

        return 'Seite nicht gefunden';
    }
}
