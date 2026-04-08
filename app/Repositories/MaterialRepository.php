<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class MaterialRepository
{
    /** @return list<array<string,mixed>> */
    public function allForUserDetailed(int $userId): array
    {
        $sql = 'SELECT m.id, m.room_id, m.name, m.type, m.color_hex, m.description, m.updated_at,
                       r.name AS room_name,
                       h.id AS house_id,
                       h.name AS house_name,
                       (SELECT COUNT(*) FROM material_store_links l WHERE l.material_id = m.id) AS link_count,
                       (SELECT COUNT(*) FROM attachments a WHERE a.material_id = m.id) AS attachment_count
                FROM materials m
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE h.owner_user_id = :user_id
                ORDER BY m.type ASC, m.name ASC, h.name ASC, r.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function allForHouse(int $houseId, int $userId): array
    {
        $sql = 'SELECT m.id, m.room_id, m.name, m.type, m.color_hex, m.description, m.updated_at,
                       r.name AS room_name,
                       (SELECT COUNT(*) FROM material_store_links l WHERE l.material_id = m.id) AS link_count,
                       (SELECT COUNT(*) FROM attachments a WHERE a.material_id = m.id) AS attachment_count
                FROM materials m
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE h.id = :house_id AND h.owner_user_id = :user_id
                ORDER BY r.name ASC, m.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'house_id' => $houseId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function allForRoom(int $roomId, int $userId): array
    {
        $typeOrder = "FIELD(m.type,'Verf','Tegel','Hout','Behang','Overig')";
        $sql = 'SELECT m.id, m.room_id, m.name, m.type, m.color_hex, m.description, m.updated_at,
                       r.name AS room_name,
                       h.id AS house_id,
                       h.name AS house_name,
                       (SELECT COUNT(*) FROM material_store_links l WHERE l.material_id = m.id) AS link_count,
                       (SELECT COUNT(*) FROM attachments a WHERE a.material_id = m.id) AS attachment_count
                FROM materials m
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE r.id = :room_id AND h.owner_user_id = :user_id
            ORDER BY ' . $typeOrder . ', m.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function recentForHouse(int $houseId, int $userId, int $limit = 20): array
    {
        $sql = 'SELECT m.id, m.name, m.type, m.color_hex, m.updated_at, r.name AS room_name,
                       (SELECT COUNT(*) FROM material_store_links l WHERE l.material_id = m.id) AS link_count,
                       (SELECT COUNT(*) FROM attachments a WHERE a.material_id = m.id) AS attachment_count
                FROM materials m
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE h.id = :house_id AND h.owner_user_id = :user_id
                ORDER BY m.updated_at DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':house_id', $houseId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $materialId, int $userId): ?array
    {
        $sql = 'SELECT m.id, m.room_id, m.type, m.name, m.color_hex, m.description
                FROM materials m
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE m.id = :id AND h.owner_user_id = :user_id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $materialId, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(int $roomId, array $data): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO materials (room_id, type, name, color_hex, description, created_at, updated_at) VALUES (:room_id, :type, :name, :color_hex, :description, NOW(), NOW())');
        $stmt->execute([
            'room_id' => $roomId,
            'type' => trim((string) ($data['type'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'color_hex' => strtoupper(trim((string) ($data['color_hex'] ?? ''))),
            'description' => trim((string) ($data['description'] ?? '')),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $materialId, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE materials SET type = :type, name = :name, color_hex = :color_hex, description = :description, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $materialId,
            'type' => trim((string) ($data['type'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'color_hex' => strtoupper(trim((string) ($data['color_hex'] ?? ''))),
            'description' => trim((string) ($data['description'] ?? '')),
        ]);
    }

    public function delete(int $materialId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM materials WHERE id = :id');
        $stmt->execute(['id' => $materialId]);
    }

    public function copyToRoom(int $materialId, int $targetRoomId, int $userId): int
    {
        $pdo = Database::connection();

        $source = $this->findForUser($materialId, $userId);
        if ($source === null) {
            throw new \RuntimeException(\App\Core\Language::translate('error.source_material_not_found'));
        }

        $stmt = $pdo->prepare('INSERT INTO materials (room_id, type, name, color_hex, description, created_at, updated_at) VALUES (:room_id, :type, :name, :color_hex, :description, NOW(), NOW())');
        $stmt->execute([
            'room_id' => $targetRoomId,
            'type' => $source['type'],
            'name' => $source['name'],
            'color_hex' => $source['color_hex'],
            'description' => $source['description'],
        ]);
        $newId = (int) $pdo->lastInsertId();

        $links = $this->linksForMaterial($materialId);
        if ($links !== []) {
            $insert = $pdo->prepare('INSERT INTO material_store_links (material_id, url, created_at) VALUES (:material_id, :url, NOW())');
            foreach ($links as $url) {
                $insert->execute(['material_id' => $newId, 'url' => $url]);
            }
        }

        return $newId;
    }

    /** @param list<string> $urls */
    public function replaceLinks(int $materialId, array $urls): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $delete = $pdo->prepare('DELETE FROM material_store_links WHERE material_id = :material_id');
        $delete->execute(['material_id' => $materialId]);

        if ($urls !== []) {
            $insert = $pdo->prepare('INSERT INTO material_store_links (material_id, url, created_at) VALUES (:material_id, :url, NOW())');
            foreach ($urls as $url) {
                $insert->execute([
                    'material_id' => $materialId,
                    'url' => $url,
                ]);
            }
        }

        $pdo->commit();
    }

    /** @return list<string> */
    public function linksForMaterial(int $materialId): array
    {
        $stmt = Database::connection()->prepare('SELECT url FROM material_store_links WHERE material_id = :material_id ORDER BY id ASC');
        $stmt->execute(['material_id' => $materialId]);
        $rows = $stmt->fetchAll();

        return array_values(array_map(static fn(array $row): string => (string) $row['url'], $rows));
    }
}
