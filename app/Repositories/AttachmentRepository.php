<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AttachmentRepository
{
    public function create(int $materialId, string $originalName, string $storedName, string $mimeType, int $sizeBytes): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO attachments (material_id, original_name, stored_name, mime_type, size_bytes, uploaded_at) VALUES (:material_id, :original_name, :stored_name, :mime_type, :size_bytes, NOW())');
        $stmt->execute([
            'material_id' => $materialId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function listForMaterial(int $materialId): array
    {
        $stmt = Database::connection()->prepare('SELECT id, original_name, stored_name, mime_type, size_bytes, uploaded_at FROM attachments WHERE material_id = :material_id ORDER BY uploaded_at DESC');
        $stmt->execute(['material_id' => $materialId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function listForHouseForUser(int $houseId, int $userId): array
    {
        $sql = 'SELECT a.id, a.original_name, a.stored_name, a.mime_type, a.size_bytes, a.uploaded_at
                FROM attachments a
                INNER JOIN materials m ON m.id = a.material_id
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE h.id = :house_id AND h.owner_user_id = :user_id
                ORDER BY a.uploaded_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'house_id' => $houseId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function listForRoomForUser(int $roomId, int $userId): array
    {
        $sql = 'SELECT a.id, a.original_name, a.stored_name, a.mime_type, a.size_bytes, a.uploaded_at
                FROM attachments a
                INNER JOIN materials m ON m.id = a.material_id
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE r.id = :room_id AND h.owner_user_id = :user_id
                ORDER BY a.uploaded_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $attachmentId, int $userId): ?array
    {
        $sql = 'SELECT a.id, a.material_id, a.original_name, a.stored_name, a.mime_type, a.size_bytes
                FROM attachments a
                INNER JOIN materials m ON m.id = a.material_id
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE a.id = :id AND h.owner_user_id = :user_id
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $attachmentId, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        $sql = 'SELECT a.id, a.original_name, a.stored_name, a.mime_type, a.size_bytes, a.uploaded_at
                FROM attachments a
                INNER JOIN materials m ON m.id = a.material_id
                INNER JOIN rooms r ON r.id = m.room_id
                INNER JOIN houses h ON h.id = r.house_id
                WHERE h.owner_user_id = :user_id
                ORDER BY a.uploaded_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }
}
