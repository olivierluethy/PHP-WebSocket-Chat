<?php

declare(strict_types=1);

namespace App\Auth;

use App\Repository\UserRepository;
use PDO;

/** Fachlogik fuer Registrierung und Login. */
final class AuthService
{
    private const USERNAME_PATTERN = '/^[a-zA-Z0-9_]{3,20}$/';
    private const PASSWORD_MIN = 8;

    private readonly UserRepository $users;

    public function __construct(PDO $pdo)
    {
        $this->users = new UserRepository($pdo);
    }

    /**
     * Registriert einen neuen Nutzer.
     *
     * @return array{ok:bool,errors:list<string>,userId:int|null}
     */
    public function register(string $username, string $password, string $passwordRepeat): array
    {
        $errors = [];

        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            $errors[] = 'Der Benutzername muss 3-20 Zeichen lang sein und darf nur Buchstaben, Zahlen und Unterstriche enthalten.';
        }

        if (strlen($password) < self::PASSWORD_MIN) {
            $errors[] = 'Das Passwort muss mindestens ' . self::PASSWORD_MIN . ' Zeichen lang sein.';
        }

        if ($password !== $passwordRepeat) {
            $errors[] = 'Die beiden Passwoerter stimmen nicht ueberein.';
        }

        if ($errors === [] && $this->users->existsByUsername($username)) {
            $errors[] = 'Dieser Benutzername ist bereits vergeben.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'userId' => null];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->users->create($username, $hash);

        return ['ok' => true, 'errors' => [], 'userId' => $userId];
    }

    /**
     * Prueft die Anmeldedaten.
     *
     * @return array{id:int,username:string}|null
     */
    public function login(string $username, string $password): ?array
    {
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            // Trotzdem hashen, um Timing-Unterschiede zu vermeiden.
            password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000');
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return ['id' => $user['id'], 'username' => $user['username']];
    }
}
