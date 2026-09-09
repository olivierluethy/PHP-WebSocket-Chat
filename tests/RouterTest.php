<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

function makeRequest(string $method, string $path): Request
{
    return new Request($method, $path, [], []);
}

test('Router: statische Route trifft', function (): void {
    $router = new Router();
    $router->get('/rooms', fn () => 'rooms');

    assertEquals('rooms', $router->dispatch(makeRequest('GET', '/rooms')));
});

test('Router: Platzhalter wird als Argument uebergeben', function (): void {
    $router = new Router();
    $router->get('/room/{id}', fn ($req, $id) => "room-{$id}");

    assertEquals('room-42', $router->dispatch(makeRequest('GET', '/room/42')));
});

test('Router: Methode wird beachtet', function (): void {
    $router = new Router();
    $router->post('/login', fn () => 'posted');
    $router->setNotFound(fn () => 'nf');

    assertEquals('posted', $router->dispatch(makeRequest('POST', '/login')));
    // GET auf /login existiert nicht -> notFound
    assertEquals('nf', $router->dispatch(makeRequest('GET', '/login')));
});

test('Router: unbekannte Route ruft notFound', function (): void {
    $router = new Router();
    $router->setNotFound(fn () => '404');

    assertEquals('404', $router->dispatch(makeRequest('GET', '/gibtsnicht')));
});
