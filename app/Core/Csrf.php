<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public static function token(): string
    {
        if (!isset($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::TOKEN_KEY];
    }

    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(?string $token): void
    {
        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? null;

        if (!is_string($token) || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
            SecurityLogger::csrfFailure();
            throw new RuntimeException(Language::translate('error.invalid_csrf_token'));
        }
    }

    public static function rotate(): void
    {
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}
