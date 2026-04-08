<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class RoomRepository
{
    /** @return list<array<string,mixed>> */
    public function allForUser(int $userId): array
    {
        $sql = 'SELECT r.id, r.house_id, r.name, r.floor, h.name AS house_name
                FROM rooms r
                INNER JOIN houses h ON h.id = r.house_id
                WHERE h.owner_user_id = :user_id
                ORDER BY h.name ASC, r.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function allForHouse(int $houseId, int $userId): array
    {
        $sql = 'SELECT r.id, r.house_id, r.name, r.floor, r.notes,
                       COUNT(m.id) AS material_count
                FROM rooms r
                INNER JOIN houses h ON h.id = r.house_id
                LEFT JOIN materials m ON m.room_id = r.id
                WHERE r.house_id = :house_id AND h.owner_user_id = :user_id
                GROUP BY r.id
                ORDER BY r.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'house_id' => $houseId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $roomId, int $userId): ?array
    {
        $sql = 'SELECT r.id, r.house_id, r.name, r.floor, r.notes,
                   h.name AS house_name,
                   h.city AS house_city
                FROM rooms r
                INNER JOIN houses h ON h.id = r.house_id
                WHERE r.id = :id AND h.owner_user_id = :user_id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $roomId, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(int $houseId, array $data): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO rooms (house_id, name, floor, notes, created_at, updated_at) VALUES (:house_id, :name, :floor, :notes, NOW(), NOW())');
        $stmt->execute([
            'house_id' => $houseId,
            'name' => trim((string) ($data['name'] ?? '')),
            'floor' => trim((string) ($data['floor'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $roomId, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE rooms SET name = :name, floor = :floor, notes = :notes, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $roomId,
            'name' => trim((string) ($data['name'] ?? '')),
            'floor' => trim((string) ($data['floor'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    public function deleteForUser(int $roomId, int $userId): void
    {
        $sql = 'DELETE r
                FROM rooms r
                INNER JOIN houses h ON h.id = r.house_id
                WHERE r.id = :id AND h.owner_user_id = :user_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'id' => $roomId,
            'user_id' => $userId,
        ]);
    }
}
