<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;
use RuntimeException;

final class Auth
{
    private const ROLE_ADMIN = 'admin';
    private const ROLE_USER = 'gebruiker';

    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function check(): bool
    {
        return self::userId() !== null;
    }

    public static function role(): ?string
    {
        $role = $_SESSION['user_role'] ?? null;
        return is_string($role) ? $role : null;
    }

    public static function language(): string
    {
        return Language::current();
    }

    public static function isAdmin(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    public static function isGebruiker(): bool
    {
        return self::role() === self::ROLE_USER;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function user(): ?array
    {
        $id = self::userId();
        if ($id === null) {
            return null;
        }

        return (new UserRepository())->findById($id);
    }

    public static function attempt(string $email, string $password): bool
    {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (($user['email_verified_at'] ?? null) === null) {
            throw new EmailNotVerifiedException();
        }

        Session::regenerate();
        Csrf::rotate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = (string) ($user['role'] ?? self::ROLE_USER);
        $_SESSION['user_language'] = Language::normalize((string) ($user['preferred_language'] ?? 'en'));
        $repo->touchLastLogin((int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            redirect('/login.php');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();

        if (!self::isAdmin()) {
            http_response_code(403);
            throw new RuntimeException(Language::translate('error.no_permission_for_action'));
        }
    }

    public static function requirePostWithCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new RuntimeException(Language::translate('error.post_only_allowed'));
        }

        Csrf::validate($_POST['_csrf'] ?? null);
    }
}
