<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * How long the Windows app takes for each page and action on this
 * computer (RecordPageTimings), for Settings → Speed check: the slowest
 * ones, to send to SchoolHub so they can be made quicker.
 */
class PageTimings
{
    /** Kept small: the oldest half goes when the file passes this. */
    protected const MAX_BYTES = 1_000_000;

    public static function file(): string
    {
        return storage_path('logs/timings.jsonl');
    }

    public static function record(string $page, int $milliseconds, int $queries): void
    {
        $file = static::file();

        if (is_file($file) && filesize($file) > self::MAX_BYTES) {
            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
            File::put($file, implode("\n", array_slice($lines, intdiv(count($lines), 2)))."\n");
        }

        File::append($file, json_encode(['p' => $page, 'ms' => $milliseconds, 'q' => $queries, 'at' => now()->toIso8601String()])."\n");
    }

    /**
     * The slowest pages and actions, by their average time.
     *
     * @return list<array{page: string, count: int, average: int, slowest: int, queries: int}>
     */
    public static function slowest(int $limit = 15): array
    {
        $lines = is_file(static::file()) ? (file(static::file(), FILE_IGNORE_NEW_LINES) ?: []) : [];

        return array_values(collect($lines)
            ->map(fn (string $line): ?array => json_decode($line, true))
            ->filter(fn ($entry): bool => is_array($entry) && isset($entry['p'], $entry['ms']))
            ->groupBy('p')
            ->map(fn ($entries, string $page): array => [
                'page' => $page,
                'count' => $entries->count(),
                'average' => (int) round($entries->avg('ms')),
                'slowest' => (int) $entries->max('ms'),
                'queries' => (int) round($entries->avg('q')),
            ])
            ->sortByDesc('average')
            ->take($limit)
            ->all());
    }

    /** The slowest pages as a short text to send. */
    public static function report(): string
    {
        $lines = array_map(
            fn (array $row): string => "{$row['page']}: {$row['average']} ms on average, slowest {$row['slowest']} ms ({$row['count']}×, {$row['queries']} queries)",
            static::slowest(10),
        );

        return "SchoolHub for Windows speed check\n".(DesktopUpdates::current() ? 'Version '.DesktopUpdates::current()."\n" : '').implode("\n", $lines);
    }

    public static function clear(): void
    {
        File::delete(static::file());
    }
}
