<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Facades\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthLogging();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Log login, logout, and failed login attempts to the audit trail,
     * including the requesting IP address and device (user agent).
     */
    protected function configureAuthLogging(): void
    {
        Event::listen(Login::class, function (Login $event) {
            Activity::causedBy($event->user)
                ->withProperties([
                    'ip' => request()->ip(),
                    'device' => request()->userAgent(),
                ])
                ->event('login')
                ->log('User logged in');
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                Activity::causedBy($event->user)
                    ->withProperties([
                        'ip' => request()->ip(),
                        'device' => request()->userAgent(),
                    ])
                    ->event('logout')
                    ->log('User logged out');
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            Activity::withProperties([
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'email' => $event->credentials['email'] ?? null,
            ])
                ->event('login_failed')
                ->log('Failed login attempt');
        });
    }
}