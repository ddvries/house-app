<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Language;

final class UserRepository
{
    /** @var list<string> */
    private const ALLOWED_ROLES = ['admin', 'gebruiker'];

    /** @var list<string> */
    private const ALLOWED_LANGUAGES = ['en', 'nl', 'de', 'fr', 'es'];

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, email, role, preferred_language, password_hash, email_verified_at FROM users WHERE email = :email LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findByVerificationToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, email, email_verified_at FROM users WHERE email_verification_token = :token LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function markEmailVerified(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function updateVerificationToken(int $id, string $token, ?string $passwordHash = null): void
    {
        $sql    = 'UPDATE users SET email_verification_token = :token, updated_at = NOW()';
        $params = ['id' => $id, 'token' => $token];
        if ($passwordHash !== null) {
            $sql .= ', password_hash = :password_hash';
            $params['password_hash'] = $passwordHash;
        }
        $sql .= ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, email, role, preferred_language, created_at, last_login_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function touchLastLogin(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT id, email, role, preferred_language, created_at, last_login_at FROM users ORDER BY created_at ASC');
        return $stmt->fetchAll();
    }

    public function countAdmins(): int
    {
        $stmt = Database::connection()->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0);
    }

    public function create(
        string $email,
        string $passwordHash,
        string $role = 'gebruiker',
        string $preferredLanguage = 'en',
        bool $verified = true,
        ?string $verificationToken = null
    ): int {
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new \InvalidArgumentException(Language::translate('error.invalid_role_with_hint'));
        }

        $normalizedLanguage = Language::normalize($preferredLanguage);
        if (!in_array($normalizedLanguage, self::ALLOWED_LANGUAGES, true)) {
            throw new \InvalidArgumentException(Language::translate('error.invalid_language_code'));
        }

        $verifiedAt = $verified ? 'NOW()' : 'NULL';

        $stmt = Database::connection()->prepare(
            "INSERT INTO users (email, role, preferred_language, password_hash, email_verified_at, email_verification_token, created_at, updated_at)
             VALUES (:email, :role, :preferred_language, :password_hash, {$verifiedAt}, :token, NOW(), NOW())"
        );
        $stmt->execute([
            'email'              => mb_strtolower(trim($email)),
            'role'               => $role,
            'preferred_language' => $normalizedLanguage,
            'password_hash'      => $passwordHash,
            'token'              => $verificationToken,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, string $email, string $role, ?string $passwordHash = null, ?string $preferredLanguage = null): void
    {
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new \InvalidArgumentException(Language::translate('error.invalid_role_with_hint'));
        }

        $params = [
            'id' => $id,
            'email' => mb_strtolower(trim($email)),
            'role' => $role,
        ];

        $sql = 'UPDATE users SET email = :email, role = :role, updated_at = NOW()';
        if ($passwordHash !== null) {
            $sql .= ', password_hash = :password_hash';
            $params['password_hash'] = $passwordHash;
        }

        if ($preferredLanguage !== null) {
            $normalizedLanguage = Language::normalize($preferredLanguage);
            if (!in_array($normalizedLanguage, self::ALLOWED_LANGUAGES, true)) {
                throw new \InvalidArgumentException(Language::translate('error.invalid_language_code'));
            }

            $sql .= ', preferred_language = :preferred_language';
            $params['preferred_language'] = $normalizedLanguage;
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function deleteById(int $id): void
    {
        // First, fetch all attachments for this user and clean up files
        $attachmentRepo = new AttachmentRepository();
        $attachments = $attachmentRepo->listForUser($id);
        
        if (!empty($attachments)) {
            $attachmentService = new \App\Services\AttachmentService();
            $attachmentService->deleteStoredFiles($attachments);
        }
        
        // Then delete the user (cascade will handle houses, rooms, materials, attachments)
        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
