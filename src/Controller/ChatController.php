<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\WsToken;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Http;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repository\RoomRepository;
use PDO;

/** Steuert Raumliste, Raumerstellung und die Chat-Seite (HTTP-Teil). */
final class ChatController
{
    private const ROOM_NAME_PATTERN = '/^[\p{L}\p{N} _\-]{2,40}$/u';

    private readonly RoomRepository $rooms;

    public function __construct(PDO $pdo)
    {
        $this->rooms = new RoomRepository($pdo);
    }

    public static function isValidRoomName(string $name): bool
    {
        return preg_match(self::ROOM_NAME_PATTERN, $name) === 1;
    }

    public function rooms(Request $request, array $errors = []): string
    {
        $this->requireAuth();

        return View::page('chat/rooms', [
            'rooms'    => $this->rooms->all(),
            'username' => (string) Session::username(),
            'errors'   => $errors,
        ], 'Raeume');
    }

    public function createRoom(Request $request): string
    {
        $this->requireAuth();

        if (!Csrf::check($request->post('_csrf'))) {
            return $this->rooms($request, ['Das Formular ist abgelaufen. Bitte erneut versuchen.']);
        }

        $name = trim((string) $request->post('name', ''));
        if (!self::isValidRoomName($name)) {
            return $this->rooms($request, ['Der Raumname muss 2-40 Zeichen lang sein (Buchstaben, Zahlen, Leer-, Binde- und Unterstrich).']);
        }

        $existing = $this->rooms->findByName($name);
        if ($existing !== null) {
            Http::redirect('/room/' . $existing['id']);
        }

        $id = $this->rooms->create($name, Session::userId());
        Http::redirect('/room/' . $id);
    }

    public function room(Request $request, int $id): string
    {
        $this->requireAuth();

        $room = $this->rooms->findById($id);
        if ($room === null) {
            Http::status(404);
            return View::page('errors/404', [], 'Nicht gefunden');
        }

        $username = (string) Session::username();
        $token = WsToken::issue((int) Session::userId(), $username, 120);

        return View::page('chat/room', [
            'room'     => $room,
            'username' => $username,
            'wsUrl'    => Config::wsPublicUrl(),
            'wsToken'  => $token,
        ], $room['name'] . ' – Chat');
    }

    private function requireAuth(): void
    {
        if (!Session::isAuthenticated()) {
            Http::redirect('/login');
        }
    }
}
