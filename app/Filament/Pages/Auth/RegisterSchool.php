<?php

namespace App\Filament\Pages\Auth;

use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolRegistered;
use App\Notifications\WelcomeToSchoolHub;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\EmailCheck;
use App\Support\PasswordStrength;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Events\Registered;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use SensitiveParameter;
use Throwable;

/**
 * A school signs itself up with only the essentials: its name, level and
 * town, and the person registering, who becomes its School Admin. The
 * free trial starts at once; everything else (logo, motto, address,
 * TIN...) is filled in later under Settings → School Profile.
 */
class RegisterSchool extends Register
{
    protected static string $layout = 'filament.auth.layout';

    protected string $view = 'filament.auth.register';

    public function getTitle(): string
    {
        return 'Register your school';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Three fields per row on wide screens, so the whole form fits
            // on one screen without scrolling.
            Section::make('Your school')
                ->compact()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])->schema([
                        TextInput::make('school_name')
                            ->label('School name')
                            ->placeholder('Your school\'s full name')
                            ->required()
                            ->maxLength(150)
                            ->columnSpan(['default' => 1, 'sm' => 2, 'xl' => 1])
                            ->autofocus(),
                        Select::make('school_type')
                            ->label('Category')
                            ->options(School::TYPES)
                            ->required()
                            ->native(false),
                        TextInput::make('city')
                            ->label('District / town')
                            ->placeholder('e.g. Wakiso')
                            ->required()
                            ->maxLength(100),
                    ]),
                ]),

            Section::make('Your account — you will be the school administrator')
                ->compact()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])->schema([
                        TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('phone')
                            ->label('Phone number')
                            ->tel()
                            ->placeholder('07XX XXX XXX')
                            ->required()
                            ->regex('/^[\d\s\+\-\(\)]{9,20}$/')
                            ->validationMessages(['regex' => 'Enter a valid phone number.']),
                        EmailCheck::apply(TextInput::make('email'))
                            ->label('Email (for signing in)')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email')
                            ->unique(School::class, 'email')
                            ->validationMessages(['unique' => 'This email is already registered on SchoolHub — sign in instead.'])
                            ->columnSpan(['default' => 1, 'sm' => 2, 'xl' => 1]),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        Checkbox::make('terms')
                            // Opens in a new tab so the half-filled form is not lost.
                            ->label(new HtmlString('I am authorised to register this school and accept the <a href="'.e(route('filament.app.legal.terms')).'" target="_blank" rel="noopener" class="shr-terms-link">terms and conditions</a>.'))
                            ->accepted()
                            ->validationMessages(['accepted' => 'Please confirm to continue.'])
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordFormComponent();

        return PasswordStrength::meter($field);
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordConfirmationFormComponent();

        return PasswordStrength::matches($field);
    }

    public function getRegisterFormAction(): Action
    {
        return parent::getRegisterFormAction()
            ->label('Create my school account')
            ->size('lg');
    }

    /**
     * Filament's registration, except the new administrator is not signed
     * in: they land on the sign-in page with a confirmation and sign in
     * with the details they just chose. The setup offer then greets them
     * on the dashboard.
     */
    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if ($this->isRegisterRateLimited($this->data['email'] ?? '')) {
            return null;
        }

        $user = $this->wrapInDatabaseTransaction(function (): Model {
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeRegister($data);

            $this->callHook('beforeRegister');
            $user = $this->handleRegistration($data);
            $this->form->model($user)->saveRelationships();
            $this->callHook('afterRegister');

            return $user;
        });

        event(new Registered($user));

        $this->sendEmailVerificationNotification($user);

        FilamentNotification::make()
            ->title('Your school account is ready')
            ->body('Sign in with the email and password you just chose to get started.')
            ->success()
            ->persistent()
            ->send();

        $this->redirect(Filament::getLoginUrl());

        return null;
    }

    /**
     * Create the school, its administrator's login and the free trial.
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        [$user, $trial] = DB::transaction(function () use ($data) {
            // Creating the school also seeds its class levels (SchoolObserver).
            $school = School::create([
                'name' => $data['school_name'],
                'slug' => $this->uniqueSlug($data['school_name']),
                'unique_code' => $this->uniqueCode(),
                'school_type' => $data['school_type'],
                'email' => $data['email'],     // the school's own address can be changed in School Profile
                'phone' => $data['phone'],
                'city' => $data['city'],
                'country' => 'Uganda',
                'currency' => 'UGX',
                'timezone' => 'Africa/Kampala',
                'contact_person' => $data['name'],
                'status' => 'active',
                // Which terms were accepted, when and by whom (config/legal.php).
                'terms_version' => config('legal.terms_version'),
                'terms_accepted_at' => now(),
                'terms_accepted_by' => "{$data['name']} <{$data['email']}>",
                'terms_accepted_ip' => request()->ip(),
            ]);

            // The trial must exist before the user: its plan sets the login limit.
            $trial = SubscriptionManager::startTrial($school);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],   // already hashed by the form
                'school_id' => $school->getKey(),
            ]);
            $user->assignRole('School Admin');

            return [$user, $trial];
        });

        // Emails are a courtesy: if the mail server is down, the school is
        // still registered and the failure is logged rather than shown.
        try {
            $user->notify(new WelcomeToSchoolHub($user->school, $trial->ends_on));
        } catch (Throwable $e) {
            report($e);
        }

        // Let the platform owner(s) know a new school has joined.
        $owners = User::role('Super Admin')->get();

        if ($owners->isNotEmpty()) {
            try {
                Notification::send($owners, new SchoolRegistered($user->school));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $user;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'school';
        $slug = $base;

        for ($i = 2; School::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    protected function uniqueCode(): string
    {
        do {
            $code = 'SH'.random_int(10000, 99999);
        } while (School::where('unique_code', $code)->exists());

        return $code;
    }
}
