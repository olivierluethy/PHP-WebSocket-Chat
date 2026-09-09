<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Core\Csrf;
use App\Core\Http;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use PDO;

/** Steuert Registrierung, Login und Logout. */
final class AuthController
{
    private readonly AuthService $auth;

    public function __construct(PDO $pdo)
    {
        $this->auth = new AuthService($pdo);
    }

    public function showLogin(Request $request): string
    {
        if (Session::isAuthenticated()) {
            Http::redirect('/rooms');
        }

        return View::page('auth/login', ['errors' => [], 'username' => ''], 'Anmelden');
    }

    public function login(Request $request): string
    {
        if (Session::isAuthenticated()) {
            Http::redirect('/rooms');
        }

        $username = trim((string) $request->post('username', ''));

        if (!Csrf::check($request->post('_csrf'))) {
            return View::page('auth/login', [
                'errors'   => ['Das Formular ist abgelaufen. Bitte erneut versuchen.'],
                'username' => $username,
            ], 'Anmelden');
        }

        $user = $this->auth->login($username, (string) $request->post('password', ''));
        if ($user === null) {
            return View::page('auth/login', [
                'errors'   => ['Benutzername oder Passwort ist falsch.'],
                'username' => $username,
            ], 'Anmelden');
        }

        Session::regenerate();
        Session::put('user_id', $user['id']);
        Session::put('username', $user['username']);

        Http::redirect('/rooms');
    }

    public function showRegister(Request $request): string
    {
        if (Session::isAuthenticated()) {
            Http::redirect('/rooms');
        }

        return View::page('auth/register', ['errors' => [], 'username' => ''], 'Registrieren');
    }

    public function register(Request $request): string
    {
        if (Session::isAuthenticated()) {
            Http::redirect('/rooms');
        }

        $username = trim((string) $request->post('username', ''));

        if (!Csrf::check($request->post('_csrf'))) {
            return View::page('auth/register', [
                'errors'   => ['Das Formular ist abgelaufen. Bitte erneut versuchen.'],
                'username' => $username,
            ], 'Registrieren');
        }

        $result = $this->auth->register(
            $username,
            (string) $request->post('password', ''),
            (string) $request->post('password_repeat', ''),
        );

        if (!$result['ok']) {
            return View::page('auth/register', [
                'errors'   => $result['errors'],
                'username' => $username,
            ], 'Registrieren');
        }

        // Nach erfolgreicher Registrierung direkt anmelden.
        Session::regenerate();
        Session::put('user_id', $result['userId']);
        Session::put('username', $username);

        Http::redirect('/rooms');
    }

    public function logout(Request $request): string
    {
        if (Csrf::check($request->post('_csrf'))) {
            Session::destroy();
        }

        Http::redirect('/login');
    }
}
