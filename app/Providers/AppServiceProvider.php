<?php

namespace App\Providers;

use App\Models\School;
use App\Observers\SchoolObserver;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $this->configureObservers();
        $this->configureDuplicateEntryHandling();
    }

    /**
     * Safety net for duplicate entries anywhere in the panels.
     *
     * Forms validate their own unique fields, but anything that slips past
     * (a race between two users, a table action, a form without the rule)
     * would otherwise hit the database's unique index and show a 500 page.
     * Turn it into a notification instead. Filament has already rolled
     * back its transaction by the time the exception reaches here.
     */
    protected function configureDuplicateEntryHandling(): void
    {
        \Livewire\on('exception', function ($component, \Throwable $e, callable $stopPropagation): void {
            if (! $e instanceof UniqueConstraintViolationException) {
                return;
            }

            Notification::make()
                ->title('Duplicate entry')
                ->body(static::duplicateEntryMessage($e))
                ->danger()
                ->persistent()
                ->send();

            $stopPropagation();
        });
    }

    /**
     * Plain-language explanation of which unique rule was broken, read
     * from the index name in the database error.
     */
    protected static function duplicateEntryMessage(UniqueConstraintViolationException $e): string
    {
        $messages = [
            'sections' => 'This class already has a section with that name.',
            'school_classes' => 'A class with that name already exists.',
            'class_levels' => 'A class level with that name already exists.',
            'houses' => 'A house with that name already exists.',
            'academic_years' => 'An academic year with that name already exists.',
            'terms' => 'That academic year already has a term with this number.',
            'residency_types' => 'A residency type with that name already exists.',
            'students' => 'A student with that admission number already exists.',
            'staff' => 'A staff member with that staff number already exists.',
            'users' => 'A user with that email address already exists.',
            'schools' => 'A school with those details already exists.',
            'fee_structures' => 'That fee has already been set up for this class and term.',
            'allowance_types' => 'An allowance type with that name already exists.',
            'deduction_types' => 'A deduction type with that name already exists.',
            'payroll_periods' => 'Payroll for that month has already been created.',
        ];

        // MySQL: "... for key 'sections.sections_school_class_id_name_unique'"
        if (preg_match("/for key '([a-z_]+)\\./", $e->getMessage(), $m) && isset($messages[$m[1]])) {
            return $messages[$m[1]] . ' Please use a different value.';
        }

        return 'A record with the same details already exists. Please change the details and try again.';
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
     * Model observers.
     */
    protected function configureObservers(): void
    {
        // Gives a new school the class levels its type implies, so the
        // admin can add classes straight away rather than meeting an
        // empty screen.
        School::observe(SchoolObserver::class);
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