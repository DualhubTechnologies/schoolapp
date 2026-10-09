<?php

namespace App\Filament\App\Resources\Users\Schemas;

use App\Models\Staff;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\EmailCheck;
use App\Support\Modules;
use App\Support\PasswordStrength;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * A login: who they are, which parts of the system they may open, and
 * which subjects they enter marks for.
 */
class UserForm
{
    /** What each role is for, in plain words, shown in the role list. */
    public const ROLE_HINTS = [
        'School Admin' => 'School Admin — everything, including users and settings',
        'Teacher' => 'Teacher — marks, class register and report cards',
        'Bursar' => 'Bursar — fees, receipts, balances and spending',
        'Accountant' => 'Accountant — fees, spending and staff payroll',
        'Admissions' => 'Admissions — admit learners, parents\' details, ID cards, texting parents',
        'Staff' => 'Other staff — you choose what they open (e.g. matron, librarian)',
        'Parent' => 'Parent — parent portal login',
        'Student' => 'Student — student portal login',
    ];

    public static function configure(Schema $schema): Schema
    {
        $isAdminRole = fn (Get $get): bool => collect($get('roles') ?? [])
            ->map(fn ($id) => Role::find($id)?->name)
            ->intersect(Modules::FULL_ACCESS_ROLES)
            ->isNotEmpty();

        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->components([
                Section::make('Account')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(150),
                        EmailCheck::apply(TextInput::make('email'))
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Another user already has this email address.']),
                        PasswordStrength::meter(TextInput::make('password'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->rule(Password::default())
                            ->same('passwordConfirmation')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                        PasswordStrength::matches(TextInput::make('passwordConfirmation'))
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->requiredWith('password')
                            ->dehydrated(false),
                        Select::make('school_id')
                            ->label('School')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->visible(fn () => auth()->user()->hasRole('Super Admin'))
                            ->required(),
                        Select::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->when(! auth()->user()->hasRole('Super Admin'), fn ($q) => $q->where('name', '!=', 'Super Admin'))
                                    ->when(! static::planAllowsParentStudentLogin(), fn ($q) => $q->whereNotIn('name', ['Parent', 'Student']))
                                    // The head first, then the bursar and accountant a school sets up first.
                                    ->orderByRaw('CASE name '.collect(Modules::ROLE_ORDER)->map(fn ($role, $i) => "WHEN '{$role}' THEN {$i}")->implode(' ').' ELSE 99 END'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Role $role): string => static::ROLE_HINTS[$role->name] ?? $role->name)
                            ->multiple()
                            ->preload()
                            ->live()
                            ->required()
                            ->helperText(fn () => static::planAllowsParentStudentLogin()
                                ? 'The role sets what a person can do by default; the modules below narrow or widen it.'
                                : 'The role sets what a person can do by default; the modules below narrow or widen it. Parent & student portal logins are not included in your plan — upgrade to Premium or Enterprise to add them.')
                            ->columnSpanFull(),
                    ]),

                Section::make('What this user can open')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        Text::make('School Admins can open everything.')
                            ->visible($isAdminRole),
                        Toggle::make('custom_access')
                            ->label('Choose modules for this user')
                            ->helperText(fn (Get $get) => 'Off: the defaults for their role — '
                                .static::roleDefaultsText($get('roles') ?? []).'.')
                            ->live()
                            // Start from the role's defaults, so ticking one extra
                            // module does not quietly take the usual ones away.
                            ->afterStateUpdated(function (bool $state, Get $get, Set $set): void {
                                if ($state && blank($get('modules'))) {
                                    $set('modules', static::roleDefaults($get('roles') ?? []));
                                }
                            })
                            ->dehydrated(false)
                            ->hidden($isAdminRole),
                        CheckboxList::make('modules')
                            ->hiddenLabel()
                            ->options(Modules::options())
                            ->descriptions(Modules::descriptions())
                            ->columns(1)
                            ->bulkToggleable()
                            ->visible(fn (Get $get) => $get('custom_access') && ! $isAdminRole($get)),
                    ]),

                Section::make('Teaching')
                    ->icon('heroicon-o-academic-cap')
                    ->description('Link the login to the person\'s staff record, then choose the subjects they teach and any class or stream they are class teacher of.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('staff_id')
                            ->label('Staff record')
                            ->options(fn (?User $record) => Staff::where('school_id', $record?->school_id ?? auth()->user()->school_id)
                                ->where(fn ($q) => $q->whereNull('user_id')->when($record, fn ($q) => $q->orWhere('user_id', $record->getKey())))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Staff $s) => [$s->id => $s->name.($s->staff_no ? " ({$s->staff_no})" : '').($s->category === 'non_teaching' ? ' · non-teaching' : '')]))
                            ->searchable()
                            ->placeholder('Not linked')
                            ->live()
                            ->dehydrated(false),
                        Select::make('teaching')
                            ->label('Subjects this user enters marks for')
                            ->options(fn () => static::classSubjectOptions())
                            ->multiple()
                            ->searchable()
                            ->placeholder('Choose class subjects…')
                            ->helperText('Choosing a subject here makes this person its subject teacher (replacing anyone else).')
                            ->disabled(fn (Get $get) => blank($get('staff_id')))
                            ->dehydrated(false),
                        Select::make('class_teacher_of')
                            ->label('Class teacher of')
                            ->options(fn () => static::streamOptions())
                            ->multiple()
                            ->searchable()
                            ->placeholder('Not a class teacher')
                            ->helperText('A class teacher can enter marks in every subject for their class or stream, takes its register and writes its report-card comments.')
                            ->disabled(fn (Get $get) => blank($get('staff_id')))
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Every class subject in the school: class subject row id => "S1 · English".
     *
     * @return array<int, string>
     */
    public static function classSubjectOptions(): array
    {
        return DB::table('class_subject')
            ->join('school_classes', 'school_classes.id', '=', 'class_subject.school_class_id')
            ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
            ->where('school_classes.school_id', auth()->user()->school_id)
            ->orderBy('school_classes.level')
            ->orderBy('school_classes.name')
            ->orderBy('subjects.sort_order')
            ->orderBy('subjects.name')
            ->get(['class_subject.id', 'school_classes.name as class', 'subjects.name as subject'])
            ->mapWithKeys(fn ($r) => [$r->id => "{$r->class} · {$r->subject}"])
            ->all();
    }

    /**
     * Every stream in the school (section id => "S1 A"), and every class
     * without streams ("class-{id}" => "P.3 (whole class)").
     *
     * @return array<int|string, string>
     */
    public static function streamOptions(): array
    {
        $schoolId = auth()->user()->school_id;

        $wholeClasses = DB::table('school_classes')
            ->where('school_id', $schoolId)
            ->whereNotExists(fn ($q) => $q->from('sections')->whereColumn('sections.school_class_id', 'school_classes.id'))
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => ["class-{$id}" => "{$name} (whole class)"])
            ->all();

        return $wholeClasses + DB::table('sections')
            ->join('school_classes', 'school_classes.id', '=', 'sections.school_class_id')
            ->where('school_classes.school_id', auth()->user()->school_id)
            ->orderBy('school_classes.level')
            ->orderBy('school_classes.name')
            ->orderBy('sections.name')
            ->get(['sections.id', 'school_classes.name as class', 'sections.name as stream'])
            ->mapWithKeys(fn ($r) => [$r->id => "{$r->class} {$r->stream}"])
            ->all();
    }

    /** Super Admin (platform owner) is never restricted; otherwise it depends on the signed-in user's school plan. */
    protected static function planAllowsParentStudentLogin(): bool
    {
        if (auth()->user()?->hasRole('Super Admin')) {
            return true;
        }

        $plan = SubscriptionManager::current(auth()->user()->school_id)?->plan;

        return $plan?->parent_student_login ?? true;
    }

    protected static function roleDefaultsText(array $roleIds): string
    {
        $modules = collect(static::roleDefaults($roleIds))
            ->map(fn ($key) => Modules::LIST[$key][0]);

        return $modules->isEmpty() ? 'no modules' : $modules->implode(', ');
    }

    /**
     * The modules the chosen roles open by default.
     *
     * @param  array<int|string>  $roleIds
     * @return list<string>
     */
    protected static function roleDefaults(array $roleIds): array
    {
        return array_values(collect($roleIds)
            ->map(fn ($id) => Role::find($id)?->name)
            ->flatMap(fn ($role) => Modules::ROLE_DEFAULTS[$role] ?? [])
            ->unique()
            // Only what this school has: no school van in a secondary school.
            ->intersect(Modules::availableKeys(auth()->user()?->school))
            ->all());
    }
}
