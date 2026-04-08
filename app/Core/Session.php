<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    /** Absolute idle timeout in seconds (1 hour). */
    private const IDLE_TIMEOUT = 3600;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_name('houseapp_session');
        session_start();

        // Expire idle sessions server-side
        if (isset($_SESSION['_last_activity']) && (time() - (int) $_SESSION['_last_activity']) > self::IDLE_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start();
        }

        $_SESSION['_last_activity'] = time();

        if (!isset($_SESSION['initialized'])) {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
            $_SESSION['csrf_rotation'] = time();
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
