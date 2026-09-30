<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * How hard the server itself is working: CPU load, memory, swap, disk,
 * how long it has been up, and how quickly the database answers. Read
 * from Linux's own counters under /proc (no shell commands), so it costs
 * next to nothing and works under PHP's usual restrictions. Anything that
 * cannot be read (Windows during development, say) is left out rather
 * than guessed.
 *
 * Each reading carries a status with the same thresholds a system
 * administrator would use by eye: load above the number of CPU cores,
 * less than a fifth of memory free, a disk more than 80% full.
 */
class ServerStats
{
    public function __construct(protected string $proc = '/proc') {}

    /**
     * @return list<array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}>
     */
    public function readings(): array
    {
        return array_values(array_filter([
            $this->load(),
            $this->memory(),
            $this->swap(),
            $this->disk(),
            $this->database(),
            $this->uptime(),
        ]));
    }

    /**
     * One line for System health's checks: the worst reading.
     *
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}|null
     */
    public function summaryCheck(): ?array
    {
        $rank = ['ok' => 0, 'warning' => 1, 'danger' => 2];
        $worst = null;

        foreach ($this->readings() as $reading) {
            if ($worst === null || $rank[$reading['status']] > $rank[$worst['status']]) {
                $worst = $reading;
            }
        }

        if ($worst === null) {
            return null;
        }

        if ($worst['status'] === 'ok') {
            return ['label' => 'Server', 'status' => 'ok', 'summary' => 'CPU, memory and disk are within safe limits.', 'fix' => null];
        }

        return ['label' => 'Server', 'status' => $worst['status'], 'summary' => $worst['label'].': '.$worst['value'].' — '.$worst['detail'], 'fix' => $worst['fix']];
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}|null
     */
    protected function load(): ?array
    {
        $load = $this->loadAverage();

        if ($load === null) {
            return null;
        }

        $cores = $this->cores();
        $perCore = $load[1] / $cores; // the 5-minute average, per core
        $status = $perCore < 0.7 ? 'ok' : ($perCore < 1.0 ? 'warning' : 'danger');

        return $this->reading(
            'load',
            'CPU load',
            $status,
            sprintf('%.2f · %.2f · %.2f', ...$load),
            "Average over 1, 5 and 15 minutes on {$cores} ".($cores === 1 ? 'core' : 'cores').'. Above '.$cores.' means work is queueing.',
            (int) min(100, round($perCore * 100)),
            $status === 'ok' ? null : 'Find what is busy with top (sorted by CPU). If it stays high, move to a VPS with more CPU cores.',
        );
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}|null
     */
    protected function memory(): ?array
    {
        $info = $this->memInfo();

        if (! isset($info['MemTotal'], $info['MemAvailable']) || $info['MemTotal'] <= 0) {
            return null;
        }

        $total = $info['MemTotal'];
        $available = $info['MemAvailable'];
        $usedPercent = (int) round(($total - $available) / $total * 100);
        $freeShare = $available / $total;
        $status = $freeShare >= 0.2 ? 'ok' : ($freeShare >= 0.1 ? 'warning' : 'danger');

        return $this->reading(
            'memory',
            'Memory',
            $status,
            $this->bytes($available * 1024).' available of '.$this->bytes($total * 1024),
            "{$usedPercent}% in use by MySQL, PHP and the system.",
            $usedPercent,
            $status === 'ok' ? null : 'Memory is running low. Restart PHP and MySQL to free it for now; for good, move to a VPS with more RAM (4 GB).',
        );
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}|null
     */
    protected function swap(): ?array
    {
        $info = $this->memInfo();

        if (! isset($info['SwapTotal'], $info['SwapFree']) || $info['SwapTotal'] <= 0) {
            return null;
        }

        $used = $info['SwapTotal'] - $info['SwapFree'];
        $percent = (int) round($used / $info['SwapTotal'] * 100);
        $status = $percent < 25 ? 'ok' : ($percent < 60 ? 'warning' : 'danger');

        return $this->reading(
            'swap',
            'Swap',
            $status,
            $this->bytes($used * 1024).' used of '.$this->bytes($info['SwapTotal'] * 1024),
            'Disk used as spare memory. A little is normal; a lot makes pages slow.',
            $percent,
            $status === 'ok' ? null : 'The server is short of memory and is using the disk instead, which is slow. Add RAM.',
        );
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}|null
     */
    protected function disk(): ?array
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if (! $total || $free === false) {
            return null;
        }

        $percent = (int) round(($total - $free) / $total * 100);
        $status = $percent < 80 ? 'ok' : ($percent < 90 ? 'warning' : 'danger');

        return $this->reading(
            'disk',
            'Disk',
            $status,
            $this->bytes($free).' free of '.$this->bytes($total),
            "{$percent}% used. Uploads, logs and the nightly backups live here.",
            $percent,
            $status === 'ok' ? null : 'The disk is filling up. Remove old files from storage/logs and copies of old backups, or add disk space.',
        );
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}|null
     */
    protected function database(): ?array
    {
        try {
            $started = hrtime(true);
            DB::select('select 1');
            $ms = (hrtime(true) - $started) / 1e6;
        } catch (Throwable) {
            return $this->reading('database', 'Database', 'danger', 'Not answering', 'SchoolHub could not reach the database.', null, 'Check that MySQL is running: systemctl status mysql.');
        }

        $size = $this->databaseSize();
        $status = $ms < 50 ? 'ok' : ($ms < 200 ? 'warning' : 'danger');

        return $this->reading(
            'database',
            'Database',
            $status,
            sprintf('Answers in %.1f ms', $ms),
            $size !== null ? 'Holds '.$this->bytes($size).' of school data.' : 'How quickly MySQL answers a simple question.',
            null,
            $status === 'ok' ? null : 'MySQL is answering slowly. Check it with top; a busy or low-memory server is the usual cause.',
        );
    }

    /**
     * @return array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}|null
     */
    protected function uptime(): ?array
    {
        $raw = @file_get_contents($this->proc.'/uptime');

        if (! $raw) {
            return null;
        }

        $seconds = (int) (float) strtok($raw, ' ');
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        return $this->reading(
            'uptime',
            'Up for',
            'ok',
            ($days > 0 ? $days.' '.($days === 1 ? 'day' : 'days').', ' : '').$hours.' '.($hours === 1 ? 'hour' : 'hours'),
            'Since the server last restarted.',
            null,
            null,
        );
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    protected function loadAverage(): ?array
    {
        $raw = @file_get_contents($this->proc.'/loadavg');

        if ($raw) {
            $parts = explode(' ', trim($raw));

            if (isset($parts[1], $parts[2])) {
                return [(float) $parts[0], (float) $parts[1], (float) $parts[2]];
            }
        }

        $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;

        return is_array($load) ? [(float) $load[0], (float) $load[1], (float) $load[2]] : null;
    }

    protected function cores(): int
    {
        $info = @file_get_contents($this->proc.'/cpuinfo');

        return $info ? max(1, preg_match_all('/^processor\s*:/m', $info)) : 1;
    }

    /** @return array<string, int> /proc/meminfo in kB */
    protected function memInfo(): array
    {
        $raw = @file_get_contents($this->proc.'/meminfo');

        if (! $raw) {
            return [];
        }

        preg_match_all('/^(\w+):\s+(\d+)/m', $raw, $m);

        return array_map('intval', array_combine($m[1], $m[2]));
    }

    /** Bytes the school data takes up in MySQL; null on other databases. */
    protected function databaseSize(): ?int
    {
        if (DB::getDriverName() !== 'mysql') {
            return null;
        }

        try {
            $row = DB::selectOne('select sum(data_length + index_length) as size from information_schema.tables where table_schema = database()');

            return isset($row->size) ? (int) $row->size : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function bytes(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i >= 3 ? number_format($bytes, 1) : number_format($bytes)).' '.$units[$i];
    }

    /**
     * @param  'ok'|'warning'|'danger'  $status
     * @return array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}
     */
    protected function reading(string $key, string $label, string $status, string $value, string $detail, ?int $percent, ?string $fix): array
    {
        return compact('key', 'label', 'status', 'value', 'detail', 'percent', 'fix');
    }
}
