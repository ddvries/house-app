<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class HouseRepository
{
    /** @return list<array<string,mixed>> */
    public function allForUser(int $userId): array
    {
        $sql = 'SELECT h.id, h.name, h.city, h.notes, h.updated_at,
                       COUNT(DISTINCT r.id) AS room_count,
                       COUNT(DISTINCT m.id) AS material_count
                FROM houses h
                LEFT JOIN rooms r ON r.house_id = h.id
                LEFT JOIN materials m ON m.room_id = r.id
                WHERE h.owner_user_id = :user_id
                GROUP BY h.id
                ORDER BY h.updated_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $houseId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, city, notes, created_at, updated_at FROM houses WHERE id = :id AND owner_user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $houseId, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO houses (owner_user_id, name, city, notes, created_at, updated_at) VALUES (:owner_user_id, :name, :city, :notes, NOW(), NOW())');
        $stmt->execute([
            'owner_user_id' => $userId,
            'name' => trim((string) ($data['name'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $houseId, int $userId, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE houses SET name = :name, city = :city, notes = :notes, updated_at = NOW() WHERE id = :id AND owner_user_id = :user_id');
        $stmt->execute([
            'id' => $houseId,
            'user_id' => $userId,
            'name' => trim((string) ($data['name'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    public function deleteForUser(int $houseId, int $userId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM houses WHERE id = :id AND owner_user_id = :user_id');
        $stmt->execute([
            'id' => $houseId,
            'user_id' => $userId,
        ]);
    }

    /** @return array{houses:int,rooms:int,materials:int} */
    public function statsForUser(int $userId): array
    {
        $pdo = Database::connection();

        $stmtHouses = $pdo->prepare('SELECT COUNT(*) as total FROM houses WHERE owner_user_id = :user_id');
        $stmtHouses->execute(['user_id' => $userId]);
        $totalHouses = (int) (($stmtHouses->fetch()['total'] ?? 0));

        $stmtRooms = $pdo->prepare('SELECT COUNT(*) as total FROM rooms r INNER JOIN houses h ON h.id = r.house_id WHERE h.owner_user_id = :user_id');
        $stmtRooms->execute(['user_id' => $userId]);
        $totalRooms = (int) (($stmtRooms->fetch()['total'] ?? 0));

        $stmtMaterials = $pdo->prepare('SELECT COUNT(*) as total FROM materials m INNER JOIN rooms r ON r.id = m.room_id INNER JOIN houses h ON h.id = r.house_id WHERE h.owner_user_id = :user_id');
        $stmtMaterials->execute(['user_id' => $userId]);
        $totalMaterials = (int) (($stmtMaterials->fetch()['total'] ?? 0));

        return [
            'houses' => $totalHouses,
            'rooms' => $totalRooms,
            'materials' => $totalMaterials,
        ];
    }
}
