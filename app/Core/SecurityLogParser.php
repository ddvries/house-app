<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Parse and filter security log entries.
 */
final class SecurityLogParser
{
    private const LOG_FILE = __DIR__ . '/../../storage/security.log';

    /**
     * Parse log file and return all entries (newest first).
     * @return list<array{timestamp:string,ip:string,event:string,context:array<string,mixed>}>
     */
    public static function all(): array
    {
        return self::readEntries();
    }

    /**
     * Filter entries by event type (case-insensitive partial match).
     * @return list<array{timestamp:string,ip:string,event:string,context:array<string,mixed>}>
     */
    public static function byEvent(string $eventFilter): array
    {
        $entries = self::readEntries();
        $filter = strtolower($eventFilter);

        return array_filter($entries, static function (array $entry) use ($filter): bool {
            return str_contains(strtolower($entry['event']), $filter);
        });
    }

    /**
     * Filter entries by IP address.
     * @return list<array{timestamp:string,ip:string,event:string,context:array<string,mixed>}>
     */
    public static function byIp(string $ip): array
    {
        $entries = self::readEntries();

        return array_filter($entries, static function (array $entry) use ($ip): bool {
            return $entry['ip'] === $ip;
        });
    }

    /**
     * Filter entries by date (YYYY-MM-DD format).
     * @return list<array{timestamp:string,ip:string,event:string,context:array<string,mixed>}>
     */
    public static function byDate(string $date): array
    {
        $entries = self::readEntries();

        return array_filter($entries, static function (array $entry) use ($date): bool {
            return str_starts_with($entry['timestamp'], $date);
        });
    }

    /**
     * Get unique IPs from log.
     * @return list<string>
     */
    public static function uniqueIps(): array
    {
        $entries = self::readEntries();
        $ips = array_map(static fn (array $e) => $e['ip'], $entries);
        return array_values(array_unique($ips));
    }

    /**
     * Get event counts by type.
     * @return array<string,int>
     */
    public static function eventCounts(): array
    {
        $entries = self::readEntries();
        $counts = [];

        foreach ($entries as $entry) {
            $event = $entry['event'];
            $counts[$event] = ($counts[$event] ?? 0) + 1;
        }

        arsort($counts);
        return $counts;
    }

    /**
     * @return list<array{timestamp:string,ip:string,event:string,context:array<string,mixed>}>
     */
    private static function readEntries(): array
    {
        if (!is_file(self::LOG_FILE)) {
            return [];
        }

        $lines = file(self::LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];
        foreach (array_reverse($lines) as $line) {
            $entry = self::parseLine($line);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array{timestamp:string,ip:string,event:string,context:array<string,mixed>}|null
     */
    private static function parseLine(string $line): ?array
    {
        // Format: [2026-04-06 14:23:45] [203.0.113.45] Event name | {"json":"context"}
        if (!preg_match('/^\[([^\]]+)\]\s*\[([^\]]+)\]\s*(.+?)(?:\s*\|\s*(.+))?$/', $line, $matches)) {
            return null;
        }

        $timestamp = $matches[1] ?? '';
        $ip = $matches[2] ?? '';
        $event = trim($matches[3] ?? '');
        $contextJson = $matches[4] ?? '';

        $context = [];
        if ($contextJson !== '') {
            $decoded = json_decode($contextJson, true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        return [
            'timestamp' => $timestamp,
            'ip' => $ip,
            'event' => $event,
            'context' => $context,
        ];
    }
}
