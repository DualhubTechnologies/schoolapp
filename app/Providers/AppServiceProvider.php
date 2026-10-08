<?php

namespace App\Providers;

use App\Exceptions\PlanLimitReached;
use App\Models\School;
use App\Observers\SchoolObserver;
use App\Support\ErrorRecorder;
use App\Support\PasswordStrength;
use App\Support\SqliteDates;
use App\Support\UndoDelete;
use Carbon\CarbonImmutable;
use Filament\Actions\Action as NotificationAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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
        // One per request, so the error page can show the reference of the
        // error recorded during that request.
        $this->app->scoped(ErrorRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        // Date-only values saved as "2026-10-01" on SQLite (the Windows app), as MySQL does.
        SqliteDates::register();
        $this->configureAuthLogging();
        $this->configureObservers();
        $this->configureDuplicateEntryHandling();
        $this->configureFilamentDefaults();
    }

    /**
     * House style for every table and pop-up in the panels. Anything that
     * sets its own value overrides these.
     */
    protected function configureFilamentDefaults(): void
    {
        // Add/edit pop-ups sized like a dialog, not stretched across the screen.
        // Saving always closes the pop-up and shows the record in the list:
        // no "save & add another" that leaves the form open.
        CreateAction::configureUsing(fn ($action) => $action->modalWidth(Width::TwoExtraLarge)->createAnother(false));
        EditAction::configureUsing(fn ($action) => $action->modalWidth(Width::TwoExtraLarge));

        // "Deleted. Undo" for a minute after deleting a record that took
        // nothing else with it (App\Support\UndoDelete).
        DeleteAction::configureUsing(function (DeleteAction $action): void {
            $snapshot = null;

            $action
                ->before(function (Model $record) use (&$snapshot, $action): void {
                    $snapshot = Filament::getCurrentPanel()?->getId() === 'app'
                        ? UndoDelete::snapshot($record, (string) ($action->getRecordTitle() ?? 'Record'))
                        : null;
                })
                ->successNotification(function (Notification $notification) use (&$snapshot): Notification {
                    if (! $snapshot) {
                        return $notification;
                    }

                    return $notification
                        ->title("{$snapshot['label']} deleted")
                        ->duration(UndoDelete::SECONDS * 1000)
                        ->actions([
                            NotificationAction::make('undo')
                                ->label('Undo')
                                ->button()
                                ->url(route('filament.app.undo-delete', ['token' => UndoDelete::remember($snapshot)])),
                        ]);
                });
        });

        // Empty lists show a neutral "nothing here yet" icon, not an "X".
        // On phones each row becomes a card with the column names beside
        // the values, instead of a wide table to scroll sideways.
        Table::configureUsing(fn ($table) => $table->emptyStateIcon('heroicon-o-inbox')->stackedOnMobile()
            // Dates the Ugandan way, day first: "8 Oct 2026", never "Oct 8, 2026".
            ->defaultDateDisplayFormat('j M Y')
            ->defaultDateTimeDisplayFormat('j M Y, H:i'));
        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat('j M Y')
            ->defaultDateTimeDisplayFormat('j M Y, H:i'));

        // Every date box opens the same calendar and shows "8 Oct 2026",
        // not the browser's own box with the American mm/dd/yyyy order.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->native(false)->displayFormat('j M Y'));
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
            // Adding past the school's plan: explain instead of an error page.
            if ($e instanceof PlanLimitReached) {
                Notification::make()->title('Plan limit reached')->body($e->getMessage())->warning()->persistent()->send();
                $stopPropagation();

                return;
            }

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
            return $messages[$m[1]].' Please use a different value.';
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

        // Behind a proxy that ends HTTPS, links and redirects would
        // otherwise come out as http://. Production is always HTTPS.
        if (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Defined in PasswordStrength so the live checklist on password
        // fields shows exactly these rules.
        Password::defaults(fn (): Password => PasswordStrength::rule());
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
