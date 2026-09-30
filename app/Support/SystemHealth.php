<?php

namespace App\Support;

use App\Console\Commands\BackupRun;
use App\Models\ErrorReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether the parts of SchoolHub that run outside the web pages are
 * actually working on this server: email, SMS, the queue worker, the
 * scheduler and backups -- and whether it is set up safely for production
 * (debug off, HTTPS, log rotation, disk space, two-step sign-in). None of these fail loudly -- emails that are only
 * logged, or a scheduler that never runs, look fine until someone asks why
 * nothing arrived -- so the platform owner sees them here (System health).
 */
class SystemHealth
{
    /** Cache key the scheduler touches every minute (routes/console.php). */
    public const SCHEDULER_HEARTBEAT = 'scheduler:heartbeat';

    /**
     * @return list<array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}>
     */
    public function checks(): array
    {
        return [
            $this->email(),
            $this->sms(),
            $this->queue(),
            $this->scheduler(),
            $this->backups(),
            $this->debugMode(),
            $this->https(),
            $this->logs(),
            $this->disk(),
            $this->ownerSignIn(),
            $this->errors(),
        ];
    }

    /** The worst status among the checks. */
    public function overall(): string
    {
        $statuses = array_column($this->checks(), 'status');

        return in_array('danger', $statuses, true) ? 'danger' : (in_array('warning', $statuses, true) ? 'warning' : 'ok');
    }

    /**
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function email(): array
    {
        $mailer = (string) config('mail.default');

        return in_array($mailer, ['log', 'array'], true)
            ? $this->row('Email', 'danger', "Not sent: MAIL_MAILER is \"{$mailer}\", so emails (sign-up codes, approvals, error alerts) only go to the log.", 'Set MAIL_MAILER=smtp and the MAIL_* settings in the server\'s .env, then run php artisan optimize.')
            : $this->row('Email', 'ok', "Sent through \"{$mailer}\" from ".config('mail.from.address').'.');
    }

    /**
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function sms(): array
    {
        $driver = (string) config('sms.driver');

        if ($driver === 'log') {
            return $this->row('SMS', 'warning', 'Not sent: SMS_DRIVER is "log", so fee reminders and parent links only go to the log.', 'Set SMS_DRIVER=africastalking and the AFRICASTALKING_* settings in .env.');
        }

        return blank(config('sms.africastalking.api_key'))
            ? $this->row('SMS', 'danger', 'SMS_DRIVER is "'.$driver.'" but AFRICASTALKING_API_KEY is empty.', 'Add the API key to .env.')
            : $this->row('SMS', 'ok', 'Sent through Africa\'s Talking ('.config('sms.africastalking.username').').');
    }

    /**
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function queue(): array
    {
        if (config('queue.default') === 'sync') {
            return $this->row('Queue worker', 'ok', 'Jobs run straight away (QUEUE_CONNECTION=sync); no worker needed.');
        }

        try {
            $oldest = DB::table('jobs')->min('created_at');
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return $this->row('Queue worker', 'warning', 'Could not read the jobs table.', null);
        }

        if ($oldest && now()->getTimestamp() - (int) $oldest > 300) {
            $minutes = (int) floor((now()->getTimestamp() - (int) $oldest) / 60);

            return $this->row('Queue worker', 'danger', "Not running: a job has been waiting {$minutes} minutes. Queued emails and student imports are not being processed.", 'Start the worker (Supervisor programme "schoolhub-queue", see docs/deployment.md).');
        }

        return $failed > 0
            ? $this->row('Queue worker', 'warning', "Running, but {$failed} job(s) have failed.", 'On the server: php artisan queue:failed, then php artisan queue:retry all once the cause is fixed.')
            : $this->row('Queue worker', 'ok', 'No jobs waiting too long, none failed.');
    }

    /**
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function scheduler(): array
    {
        $last = Cache::get(self::SCHEDULER_HEARTBEAT);

        if (! $last) {
            return $this->row('Scheduler', 'danger', 'Has never run: subscription reminders, backups and clean-up are not happening.', 'Add the cron entry: * * * * * cd /var/www/schoolapp && php artisan schedule:run (see docs/deployment.md).');
        }

        $at = Carbon::createFromTimestamp((int) $last);

        return $at->lt(now()->subMinutes(5))
            ? $this->row('Scheduler', 'danger', 'Stopped: last ran '.$at->diffForHumans().'.', 'Check the cron entry for php artisan schedule:run.')
            : $this->row('Scheduler', 'ok', 'Running: last ran '.$at->diffForHumans().'.');
    }

    /**
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function backups(): array
    {
        $last = Cache::get(BackupRun::LAST_SUCCESS);

        if (! $last) {
            return $this->row('Backups', 'danger', 'No backup has completed on this server yet.', 'Needs the scheduler running (backups at 01:30 daily), or run php artisan backup:run once now.');
        }

        $at = Carbon::parse((string) $last);

        return $at->lt(now()->subHours(36))
            ? $this->row('Backups', 'danger', 'Last successful backup '.$at->diffForHumans().'.', 'Check Error reports for a backup failure, and that the scheduler is running.')
            : $this->row('Backups', 'ok', 'Last successful backup '.$at->diffForHumans().' (kept in storage/app/backups). Copy them off the server too.');
    }

    /**
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function debugMode(): array
    {
        return config('app.debug') && app()->environment('production')
            ? $this->row('Debug mode', 'danger', 'APP_DEBUG is on in production: users would see code and settings instead of the friendly error pages.', 'Set APP_DEBUG=false in .env, then php artisan optimize.')
            : $this->row('Debug mode', 'ok', 'Off for users (APP_ENV='.app()->environment().').');
    }

    /**
     * Production should only be reached over HTTPS, with the session cookie
     * marked secure so it never travels over plain HTTP.
     *
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function https(): array
    {
        if (! app()->environment('production')) {
            return $this->row('HTTPS', 'ok', 'Not checked outside production.');
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            return $this->row('HTTPS', 'danger', 'APP_URL is not https://: links in emails and SMS, and photo links, use plain HTTP.', 'Set APP_URL=https://your-domain in .env (with a certificate on the server), then php artisan optimize.');
        }

        return config('session.secure')
            ? $this->row('HTTPS', 'ok', 'Links use HTTPS and the sign-in cookie is HTTPS-only.')
            : $this->row('HTTPS', 'warning', 'The sign-in cookie is not marked HTTPS-only.', 'Set SESSION_SECURE_COOKIE=true in .env, then php artisan optimize.');
    }

    /**
     * A single log file grows until the disk is full; daily files are
     * cleaned up after LOG_DAILY_DAYS.
     *
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function logs(): array
    {
        $channel = (string) config('logging.default');
        $channels = $channel === 'stack' ? (array) config('logging.channels.stack.channels') : [$channel];
        $level = (string) config('logging.channels.'.($channels[0] ?? 'single').'.level', 'debug');

        if (in_array('single', $channels, true) && app()->environment('production')) {
            return $this->row('Log files', 'warning', 'One log file that is never cleaned up: it grows until the disk is full.', 'Set LOG_STACK=daily and LOG_LEVEL=warning in .env (files are kept 14 days), then php artisan optimize.');
        }

        return $this->row('Log files', 'ok', 'Channel: '.implode(', ', $channels).", level {$level}.");
    }

    /**
     * Photos, backups and logs all live on the server's disk.
     *
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if (! $free || ! $total) {
            return $this->row('Disk space', 'warning', 'Could not read the free disk space.');
        }

        $gb = round($free / 1024 ** 3, 1);
        $percent = (int) round($free / $total * 100);
        $summary = "{$gb} GB free ({$percent}%).";

        return match (true) {
            $gb < 1 || $percent < 5 => $this->row('Disk space', 'danger', "Almost full: {$summary} Uploads, backups and logs will start failing.", 'Delete old backups once they are copied off the server, or add disk space.'),
            $gb < 5 || $percent < 15 => $this->row('Disk space', 'warning', "Getting low: {$summary}", 'Plan more disk space; photos and backups grow every term.'),
            default => $this->row('Disk space', 'ok', $summary),
        };
    }

    /**
     * The platform owner's account can open every school's data.
     *
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function ownerSignIn(): array
    {
        $owners = User::role('Super Admin')->get();
        $without = $owners->filter(fn (User $user) => blank($user->getAppAuthenticationSecret()))->count();

        return $without > 0
            ? $this->row('Owner sign-in', 'warning', "{$without} platform owner account(s) sign in with a password only.", 'Open your profile (top right) and set up two-step sign-in with an authenticator app.')
            : $this->row('Owner sign-in', 'ok', 'Platform owner accounts use two-step sign-in.');
    }

    /**
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function errors(): array
    {
        $open = ErrorReport::whereNull('resolved_at')->count();

        return $open > 0
            ? $this->row('Errors', 'warning', "{$open} open error report(s).", 'See Error reports.')
            : $this->row('Errors', 'ok', 'No open error reports.');
    }

    /**
     * @param  'ok'|'warning'|'danger'  $status
     * @return array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}
     */
    protected function row(string $label, string $status, string $summary, ?string $fix = null): array
    {
        return ['label' => $label, 'status' => $status, 'summary' => $summary, 'fix' => $fix];
    }
}
