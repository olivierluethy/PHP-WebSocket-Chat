<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Datenzugriff fuer Chatraeume (Gruppenchats). */
final class RoomRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $name, ?int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rooms (name, created_by) VALUES (:n, :c)'
        );
        $stmt->execute([':n' => $name, ':c' => $createdBy]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array{id:int,name:string,created_by:int|null,created_at:string}> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT * FROM rooms ORDER BY name COLLATE NOCASE ASC')->fetchAll();

        return array_map([$this, 'normalize'], $rows);
    }

    /** @return array{id:int,name:string,created_by:int|null,created_at:string}|null */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rooms WHERE name = :n');
        $stmt->execute([':n' => $name]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->normalize($row);
    }

    /** @return array{id:int,name:string,created_by:int|null,created_at:string}|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rooms WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->normalize($row);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,name:string,created_by:int|null,created_at:string}
     */
    private function normalize(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'name'       => (string) $row['name'],
            'created_by' => $row['created_by'] === null ? null : (int) $row['created_by'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
