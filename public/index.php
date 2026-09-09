<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\ChatController;
use App\Core\Http;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Database\Connection;
use App\Database\Migrator;

require \dirname(__DIR__) . '/config/config.php';

Session::start();

// Datenbank vorbereiten (idempotent).
$pdo = Connection::pdo();
Migrator::migrate($pdo);

$auth = new AuthController($pdo);
$chat = new ChatController($pdo);

$router = new Router();

// Startseite: je nach Login weiterleiten.
$router->get('/', static function (): never {
    Http::redirect(Session::isAuthenticated() ? '/rooms' : '/login');
});

$router->get('/login', static fn (Request $r) => $auth->showLogin($r));
$router->post('/login', static fn (Request $r) => $auth->login($r));
$router->get('/register', static fn (Request $r) => $auth->showRegister($r));
$router->post('/register', static fn (Request $r) => $auth->register($r));
$router->post('/logout', static fn (Request $r) => $auth->logout($r));

$router->get('/rooms', static fn (Request $r) => $chat->rooms($r));
$router->post('/rooms', static fn (Request $r) => $chat->createRoom($r));
$router->get('/room/{id}', static fn (Request $r, string $id) => $chat->room($r, (int) $id));

$router->setNotFound(static function (): string {
    Http::status(404);
    return \App\Core\View::page('errors/404', [], 'Nicht gefunden');
});

$response = $router->dispatch(Request::fromGlobals());

if (is_string($response)) {
    echo $response;
}
