<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * Nightly backup: the whole database and the uploaded files (photos,
 * signatures, logos), kept for a set number of days in
 * storage/app/backups. Scheduled in routes/console.php; shown on System
 * health.
 *
 * On the server (MySQL) the database is a compressed SQL dump and the
 * files a .tar.gz. In the Windows app (SQLite) the database is a
 * compressed copy of the database file, made with VACUUM INTO so it is
 * consistent even while the app is in use, and the files are a .zip,
 * since Windows has no mysqldump or tar to rely on.
 *
 * These copies are on the same server as the data. Copy the folder off
 * the server as well (see docs/operations.md): a backup that dies with
 * the server is not a backup.
 *
 * A failure is reported like any other error, so the platform owner is
 * emailed and sees it under Error reports.
 */
class BackupRun extends Command
{
    /** Cache key holding the time of the last successful backup. */
    public const LAST_SUCCESS = 'backup:last-success';

    protected $signature = 'backup:run
        {--keep=14 : Days of backups to keep}
        {--no-files : Back up the database only}';

    protected $description = 'Back up the database and uploaded files to storage/app/backups';

    public function handle(): int
    {
        $folder = self::folder();
        File::ensureDirectoryExists($folder);
        $stamp = now()->format('Y-m-d-His');

        try {
            $database = $this->backupDatabase("{$folder}/schoolhub-db-{$stamp}");
            $files = $this->option('no-files') ? null : $this->backupFiles("{$folder}/schoolhub-files-{$stamp}");
        } catch (RuntimeException $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $removed = $this->removeOld($folder, max(1, (int) $this->option('keep')));

        Cache::forever(self::LAST_SUCCESS, now()->toIso8601String());

        $this->info('Database: '.basename($database));
        $this->info($files ? 'Files: '.basename($files) : 'Files: skipped');
        $this->info("Removed {$removed} old backup file(s).");

        return self::SUCCESS;
    }

    public static function folder(): string
    {
        return storage_path('app/backups');
    }

    /** $base: the path without its extension, which depends on the database. */
    protected function backupDatabase(string $base): string
    {
        $db = config('database.connections.'.config('database.default'));

        if (($db['driver'] ?? null) === 'sqlite') {
            return $this->backupSqlite((string) $db['database'], $base.'.sqlite.gz');
        }

        $target = $base.'.sql.gz';

        if (($db['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Database backup needs MySQL; the current connection is '.($db['driver'] ?? 'unknown').'.');
        }

        $dump = implode(' ', array_map('escapeshellarg', [
            'mysqldump', '--single-transaction', '--quick', '--routines', '--no-tablespaces',
            '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'], $db['database'],
        ]));

        // The password goes in the environment, not the command line, so it
        // never shows in the server's process list.
        $result = Process::env(['MYSQL_PWD' => (string) $db['password']])
            ->timeout(1800)
            ->run(['bash', '-c', 'set -o pipefail; '.$dump.' | gzip > '.escapeshellarg($target)]);

        if ($result->failed()) {
            File::delete($target);

            throw new RuntimeException('Database backup failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        return $target;
    }

    /**
     * A consistent copy of the SQLite database (VACUUM INTO writes a clean
     * snapshot while the app keeps working), gzipped.
     */
    protected function backupSqlite(string $database, string $target): string
    {
        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            throw new RuntimeException('Database backup failed: the SQLite database file was not found.');
        }

        $copy = $target.'.tmp';
        File::delete($copy);

        try {
            DB::statement('VACUUM INTO ?', [$copy]);

            $in = fopen($copy, 'rb');
            $out = gzopen($target, 'wb6');

            if (! $in || ! $out) {
                throw new RuntimeException('Database backup failed: could not write '.$target.'.');
            }

            while (! feof($in)) {
                gzwrite($out, (string) fread($in, 1 << 20));
            }

            fclose($in);
            gzclose($out);
        } catch (RuntimeException $e) {
            File::delete($target);

            throw $e;
        } catch (\Throwable $e) {
            File::delete($target);

            throw new RuntimeException('Database backup failed: '.$e->getMessage(), previous: $e);
        } finally {
            File::delete($copy);
        }

        return $target;
    }

    /** $base: the path without its extension (.tar.gz on the server, .zip where tar is missing). */
    protected function backupFiles(string $base): ?string
    {
        $folders = array_values(array_filter(['private/uploads', 'public'], fn (string $path): bool => is_dir(storage_path('app/'.$path))));

        if ($folders === []) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->zipFiles($folders, $base.'.zip');
        }

        $target = $base.'.tar.gz';

        $result = Process::timeout(1800)->run(['tar', '-czf', $target, '-C', storage_path('app'), ...$folders]);

        if ($result->failed()) {
            File::delete($target);

            throw new RuntimeException('File backup failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        return $target;
    }

    /**
     * The uploaded files as a .zip, for Windows.
     *
     * @param  list<string>  $folders  relative to storage/app
     */
    protected function zipFiles(array $folders, string $target): string
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File backup failed: could not create '.$target.'.');
        }

        foreach ($folders as $folder) {
            foreach (File::allFiles(storage_path('app/'.$folder)) as $file) {
                $zip->addFile($file->getPathname(), $folder.'/'.str_replace('\\', '/', $file->getRelativePathname()));
            }
        }

        if (! $zip->close()) {
            File::delete($target);

            throw new RuntimeException('File backup failed: could not finish '.$target.'.');
        }

        return $target;
    }

    /** Delete backups older than $days; returns how many were removed. */
    protected function removeOld(string $folder, int $days): int
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $removed = 0;

        foreach (File::glob($folder.'/schoolhub-*') as $file) {
            if (File::lastModified($file) < $cutoff && File::delete($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
