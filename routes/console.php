<?php

use App\Models\ErrorOccurrence;
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

// Proof the scheduler is running, shown on System health.
Schedule::call(fn () => Cache::forever(SystemHealth::SCHEDULER_HEARTBEAT, now()->getTimestamp()))
    ->everyMinute()
    ->name('scheduler-heartbeat')
    ->onOneServer();
