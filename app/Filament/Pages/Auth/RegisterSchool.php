<?php

namespace App\Filament\Pages\Auth;

use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolRegistered;
use App\Services\Subscriptions\SubscriptionManager;
use Filament\Actions\Action;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use SensitiveParameter;

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
                            ->placeholder('e.g. St. Mary\'s College Kisubi')
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
                        TextInput::make('email')
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
                            ->label('I am authorised to register this school and accept the terms of use.')
                            ->accepted()
                            ->validationMessages(['accepted' => 'Please confirm to continue.'])
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public function getRegisterFormAction(): Action
    {
        return parent::getRegisterFormAction()
            ->label('Create my school account')
            ->size('lg');
    }

    /**
     * Create the school, its administrator's login and the free trial.
     * Filament then signs the new administrator in.
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $user = DB::transaction(function () use ($data) {
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
            ]);

            // The trial must exist before the user: its plan sets the login limit.
            SubscriptionManager::startTrial($school);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],   // already hashed by the form
                'school_id' => $school->getKey(),
            ]);
            $user->assignRole('School Admin');

            return $user;
        });

        // Let the platform owner(s) know a new school has joined.
        $owners = User::role('Super Admin')->get();

        if ($owners->isNotEmpty()) {
            Notification::send($owners, new SchoolRegistered($user->school));
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
            $code = 'SH' . random_int(10000, 99999);
        } while (School::where('unique_code', $code)->exists());

        return $code;
    }
}
