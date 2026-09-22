<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
