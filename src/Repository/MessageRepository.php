<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Datenzugriff fuer Chatnachrichten (Persistenz + Verlauf). */
final class MessageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Speichert eine Nachricht als Rohtext (Escaping erfolgt erst bei der Ausgabe). */
    public function add(int $roomId, ?int $userId, string $username, string $body): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (room_id, user_id, username_snapshot, body)
             VALUES (:r, :u, :name, :b)'
        );
        $stmt->execute([
            ':r'    => $roomId,
            ':u'    => $userId,
            ':name' => $username,
            ':b'    => $body,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Liefert die letzten $limit Nachrichten eines Raums in chronologischer
     * Reihenfolge (aelteste zuerst).
     *
     * @return list<array{id:int,username:string,body:string,created_at:string}>
     */
    public function recent(int $roomId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $stmt = $this->pdo->prepare(
            'SELECT id, username_snapshot, body, created_at
             FROM messages WHERE room_id = :r ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':r', $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $rows = array_reverse($rows);

        return array_map(static fn (array $row): array => [
            'id'         => (int) $row['id'],
            'username'   => (string) $row['username_snapshot'],
            'body'       => (string) $row['body'],
            'created_at' => (string) $row['created_at'],
        ], $rows);
    }
}
