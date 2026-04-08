<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralized security event logging.
 * Logs intrusion attempts, suspicious behavior, and security events.
 */
final class SecurityLogger
{
    private const LOG_FILE = __DIR__ . '/../../storage/security.log';

    /**
     * Log a security event (intrusion attempt, suspicious activity, etc).
     */
    public static function log(string $event, ?string $ip = null, ?array $context = null): void
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $timestamp = date('Y-m-d H:i:s');
        $message = "[{$timestamp}] [{$ip}] {$event}";

        if ($context !== null && $context !== []) {
            $message .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        $dir = dirname(self::LOG_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        error_log($message . PHP_EOL, 3, self::LOG_FILE);
    }

    /**
     * Log a failed login attempt.
     */
    public static function loginFailure(string $email, int $attemptNumber, int $maxAttempts, ?string $ip = null): void
    {
        self::log(
            "Login failure",
            $ip,
            [
                'email' => $email,
                'attempt' => $attemptNumber,
                'max_attempts' => $maxAttempts,
            ]
        );
    }

    /**
     * Log a rate-limit lockout.
     */
    public static function rateLimitLockout(string $key, int $lockoutSeconds, ?string $ip = null): void
    {
        self::log(
            "Rate limit lockout",
            $ip,
            [
                'key' => $key,
                'lockout_seconds' => $lockoutSeconds,
            ]
        );
    }

    /**
     * Log a CSRF token validation failure.
     */
    public static function csrfFailure(?string $ip = null): void
    {
        self::log("CSRF token validation failed", $ip);
    }

    /**
     * Log a path traversal attempt.
     */
    public static function pathTraversalAttempt(string $attemptedPath, ?string $ip = null): void
    {
        self::log(
            "Path traversal attempt detected",
            $ip,
            ['attempted_path' => $attemptedPath]
        );
    }

    /**
     * Log an invalid file upload.
     */
    public static function invalidUpload(string $reason, ?string $ip = null, ?array $context = null): void
    {
        self::log("Invalid upload", $ip, array_merge(['reason' => $reason], $context ?? []));
    }

    /**
     * Log unauthorized access attempt.
     */
    public static function unauthorizedAccess(string $resource, ?string $ip = null, ?array $context = null): void
    {
        self::log("Unauthorized access attempt", $ip, array_merge(['resource' => $resource], $context ?? []));
    }
}
