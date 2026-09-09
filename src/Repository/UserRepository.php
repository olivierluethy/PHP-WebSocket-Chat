<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Datenzugriff fuer Benutzer. Ausschliesslich Prepared Statements. */
final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $username, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash) VALUES (:u, :h)'
        );
        $stmt->execute([':u' => $username, ':h' => $passwordHash]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,username:string,password_hash:string,created_at:string}|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->normalize($row);
    }

    /** @return array{id:int,username:string,password_hash:string,created_at:string}|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->normalize($row);
    }

    public function existsByUsername(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,username:string,password_hash:string,created_at:string}
     */
    private function normalize(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'username'      => (string) $row['username'],
            'password_hash' => (string) $row['password_hash'],
            'created_at'    => (string) $row['created_at'],
        ];
    }
}
