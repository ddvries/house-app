<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple file-based rate limiter.
 * Tracks failed attempts per hashed key (IP + action).
 * No external dependencies required.
 */
final class RateLimiter
{
    public const MAX_ATTEMPTS  = 5;
    private const DECAY_SECONDS = 300;  // 5 minutes
    private const LOCKOUT_EXTRA = 600;  // 10-minute lockout after max hit

    private static function storageDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/rate';
    }

    private static function filePath(string $key): string
    {
        return self::storageDir() . '/' . hash('sha256', $key) . '.json';
    }

    private static function read(string $key): array
    {
        $file = self::filePath($key);
        if (!is_file($file)) {
            return ['attempts' => 0, 'first_attempt' => 0, 'locked_until' => 0];
        }

        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : ['attempts' => 0, 'first_attempt' => 0, 'locked_until' => 0];
    }

    /**
     * Get current rate limit state for a key (for diagnostics/logging).
     * @return array<string,int>
     */
    public static function state(string $key): array
    {
        return self::read($key);
    }

    private static function write(string $key, array $data): void
    {
        $dir = self::storageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents(self::filePath($key), json_encode($data), LOCK_EX);
    }

    /**
     * Returns true if the key is currently rate-limited (too many attempts).
     */
    public static function tooManyAttempts(string $key): bool
    {
        $data = self::read($key);

        if ($data['locked_until'] > time()) {
            return true;
        }

        // Reset window if decay period has passed since first attempt
        if ($data['first_attempt'] > 0 && (time() - (int) $data['first_attempt']) > self::DECAY_SECONDS) {
            self::clear($key);
            return false;
        }

        return (int) $data['attempts'] >= self::MAX_ATTEMPTS;
    }

    /**
     * Record a failed attempt.
     */
    public static function hit(string $key): void
    {
        $data = self::read($key);

        // Reset window if decay period has passed
        if ($data['first_attempt'] > 0 && (time() - (int) $data['first_attempt']) > self::DECAY_SECONDS) {
            $data = ['attempts' => 0, 'first_attempt' => 0, 'locked_until' => 0];
        }

        if ($data['first_attempt'] === 0) {
            $data['first_attempt'] = time();
        }

        $data['attempts'] = (int) $data['attempts'] + 1;

        if ($data['attempts'] >= self::MAX_ATTEMPTS) {
            $data['locked_until'] = time() + self::LOCKOUT_EXTRA;
        }

        self::write($key, $data);
    }

    /**
     * Clear the rate limit record (e.g. on successful login).
     */
    public static function clear(string $key): void
    {
        $file = self::filePath($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Seconds remaining in the current lockout, or 0 if not locked.
     */
    public static function secondsUntilUnlocked(string $key): int
    {
        $data = self::read($key);
        $remaining = (int) $data['locked_until'] - time();
        return max(0, $remaining);
    }
}
