<?php

use App\Models\ErrorOccurrence;
use App\Models\SiteVisit;
use App\Support\Edition;
use App\Support\SystemHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily reminders to schools whose trial or subscription is ending
// (see config/subscriptions.php). Needs the scheduler running:
// `php artisan schedule:work` locally, or a cron entry in production.
Schedule::command('subscriptions:remind')
    ->when(fn (): bool => Edition::isServer())
    ->dailyAt(config('subscriptions.reminder_time', '08:00'))
    ->timezone(config('subscriptions.reminder_timezone', 'Africa/Kampala'))
    ->withoutOverlapping()
    ->onOneServer();

// Error occurrences older than 60 days (the reports keep their counts).
Schedule::command('model:prune', ['--model' => [ErrorOccurrence::class]])
    ->dailyAt('02:30')
    ->onOneServer();

// Nightly backup of the database and uploaded files (storage/app/backups).
Schedule::command('backup:run')
    ->dailyAt('01:30')
    ->timezone('Africa/Kampala')
    ->withoutOverlapping()
    ->onOneServer();

// Visitor locations (Website visitors page): MaxMind publishes a new
// GeoLite2 City database twice a week; refresh it weekly. Skipped until
// the MaxMind account details are in .env.
Schedule::command('geoip:update')
    ->weeklyOn(3, '03:15')
    ->timezone('Africa/Kampala')
    ->when(fn (): bool => filled(config('services.maxmind.license_key')))
    ->withoutOverlapping()
    ->onOneServer();

// Website visits older than two years.
Schedule::command('model:prune', ['--model' => [SiteVisit::class]])
    ->dailyAt('02:45')
    ->onOneServer();

// Windows app: texts written while offline go out once it is back online.
Schedule::command('sms:send-queued')
    ->everyFiveMinutes()
    ->when(fn (): bool => Edition::isDesktop())
    ->withoutOverlapping()
    ->onOneServer();

// Proof the scheduler is running, shown on System health.
Schedule::call(fn () => Cache::forever(SystemHealth::SCHEDULER_HEARTBEAT, now()->getTimestamp()))
    ->everyMinute()
    ->name('scheduler-heartbeat')
    ->onOneServer();
