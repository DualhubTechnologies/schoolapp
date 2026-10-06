<?php

namespace App\Filament\App\Resources\Students\Schemas;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section as StreamSection;
use App\Models\Student;
use App\Support\EmailCheck;
use App\Support\ImageShrinker;
use App\Support\PrivateFiles;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        $card = fn (string $name): array => ['class' => 'sh-student-card sh-student-card--'.$name];

        return $schema
            ->columns(1)
            ->components([
                // The learner at a glance: photo, class, status, parent,
                // fees balance, profile completeness and quick actions.
                View::make('filament.app.students.profile-summary')
                    ->visible(fn (?Student $record): bool => $record?->exists ?? false)
                    ->columnSpanFull(),

                Grid::make(['default' => 1, 'xl' => 12])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'sh-student-form sh-student-layout'])
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 7])
                            ->schema([
                                Section::make('Personal details')
                                    ->description('Photo, name and personal information.')
                                    ->icon('heroicon-o-user')
                                    ->extraAttributes($card('identity'))
                                    ->columns(['default' => 1, 'md' => 3])
                                    ->schema(static::personalFields()),

                                Section::make('Admission and identification')
                                    ->description('Registration number, status and official identifiers.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes($card('admission'))
                                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->schema(static::admissionFields()),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 5])
                            ->schema([
                                Section::make('Class and enrolment')
                                    ->description('Class, stream, subjects, residency and house.')
                                    ->icon('heroicon-o-academic-cap')
                                    ->extraAttributes($card('enrollment'))
                                    ->columns(['default' => 1, 'md' => 2])
                                    ->schema(static::enrollmentFields()),

                                Section::make('Parent and contact')
                                    ->description('Parent or guardian, and the learner\'s own contacts.')
                                    ->icon('heroicon-o-phone')
                                    ->extraAttributes($card('contact'))
                                    ->columns(['default' => 1, 'md' => 2])
                                    ->schema(static::contactFields()),
                            ]),

                        Section::make('Address and welfare')
                            ->description('Home address and what staff may need to know for the learner\'s safety.')
                            ->icon('heroicon-o-heart')
                            ->extraAttributes($card('welfare'))
                            ->columnSpanFull()
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema(static::welfareFields()),
                    ]),
            ]);
    }

    /**
     * Photo, name and personal details.
     *
     * @return array<int, Component>
     */
    public static function personalFields(): array
    {
        return [
            ImageShrinker::noBrowserResize(FileUpload::make('photo')
                ->label('Student photo')
                ->image()
                ->avatar()
                ->imageEditor()
                // Square crop, saved in the photo's own format. The circle
                // cropper saved a full-size PNG, often too big to upload, and
                // a round picture does not fill the square frame on ID cards.
                ->imageEditorAspectRatioOptions(['1:1'])
                ->saveUploadedFileUsing(ImageShrinker::saveWithin(800, 800, square: true))
                ->maxSize(10240)
                ->disk(PrivateFiles::DISK)
                ->visibility('private')
                ->directory('students')
                ->alignCenter()
                ->helperText('Upload a clear photo. You can crop it before saving.')
                ->extraFieldWrapperAttributes([
                    'class' => 'sh-student-photo',
                ])
                ->columnSpan(['default' => 1, 'md' => 1])),

            Grid::make(['default' => 1, 'sm' => 2])
                ->columnSpan(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('first_name')
                        ->label('First name')
                        ->placeholder('Enter first name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('last_name')
                        ->label('Last name')
                        ->placeholder('Enter last name')
                        ->required()
                        ->maxLength(100),

                    Select::make('gender')
                        ->label('Sex')
                        ->options(Student::GENDERS)
                        ->placeholder('Select sex')
                        ->native(false),

                    DatePicker::make('date_of_birth')
                        ->label('Birth date')
                        ->maxDate(now()),

                    TextInput::make('nin')
                        ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'National Identification Number, if the learner has a national ID.')
                        ->label('National ID')
                        ->maxLength(100),
                ]),
        ];
    }

    /**
     * Registration number, admission date, status and official identifiers.
     *
     * @return array<int, Component>
     */
    public static function admissionFields(): array
    {
        return [
            static::admissionNumberField(),

            DatePicker::make('admission_date')
                ->label('Admission date')
                ->default(now()),

            Select::make('status')
                ->label('Status')
                ->options(Student::STATUSES)
                ->default('active')
                ->native(false)
                ->required(),

            TextInput::make('lin')
                ->hintIcon('heroicon-m-question-mark-circle', tooltip: "Learner Identification Number from the Ministry of Education's EMIS system.")
                ->label('LIN')
                ->placeholder('Learner Identification Number')
                ->maxLength(100),

            TextInput::make('schoolpay_code')
                ->label('SchoolPay code')
                ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Only if your school uses SchoolPay: the code parents pay fees to for this learner. It is shown on fee reminders, letters and the parent page.')
                ->placeholder('e.g. 1004567890')
                ->maxLength(30),

            // Only the platform owner picks a school; everyone else's
            // students belong to their own school (set on create).
            Select::make('school_id')
                ->label('School')
                ->relationship('school', 'name')
                ->default(fn () => auth()->user()?->school_id)
                ->visible(fn () => auth()->user()?->hasRole('Super Admin') ?? false)
                ->required(),
        ];
    }

    /**
     * Class, stream, curriculum choices, residency, house.
     *
     * @return array<int, Component>
     */
    public static function enrollmentFields(): array
    {
        return [
            Select::make('school_class_id')
                ->label('Class')
                ->relationship(
                    'schoolClass',
                    'name',
                    fn (Builder $query) => static::scopeToSchool($query),
                )
                ->searchable()
                ->preload()
                ->live()
                ->placeholder('Select class')
                ->afterStateUpdated(fn (Set $set) => $set('section_id', null))
                ->required(),

            Select::make('section_id')
                ->label('Class stream')
                ->relationship(
                    'section',
                    'name',
                    fn (Builder $query, Get $get) => static::scopeToSchool($query)
                        ->where('school_class_id', $get('school_class_id') ?? 0),
                )
                ->searchable()
                ->preload()
                ->placeholder('Select stream')
                // Classes without streams (most schools) never see this.
                ->visible(fn (Get $get): bool => filled($get('school_class_id'))
                    && StreamSection::where('school_class_id', $get('school_class_id'))->exists())
                ->disabled(fn (Get $get): bool => blank($get('school_class_id')))
                ->helperText(fn (Get $get): string => blank($get('school_class_id'))
                    ? 'Select a class first.'
                    : 'Choose the student stream.'),

            // A-Level: the combination decides which three
            // principal subjects count towards points.
            Select::make('combination_id')
                ->label('A-Level combination')
                ->relationship('combination', 'name', fn (Builder $query) => static::scopeToSchool($query)->where('is_active', true))
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.($record->description ? ' — '.$record->description : ''))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => static::classCurriculum($get('school_class_id')) === 'a_level')
                ->helperText('The subsidiary (Sub-Maths / Sub-ICT) comes with the combination unless changed below.'),

            // Electives (O-Level) or a different subsidiary
            // (A-Level): subjects this student takes that are
            // not compulsory in the class.
            Select::make('electives')
                ->label(fn (Get $get): string => static::classCurriculum($get('school_class_id')) === 'a_level' ? 'Subsidiary (if not the combination\'s)' : 'Elective subjects')
                ->relationship(
                    'electives',
                    'name',
                    fn (Builder $query, Get $get) => $query
                        ->whereIn('subjects.id', SchoolClass::find($get('school_class_id'))?->subjects()->wherePivot('is_compulsory', false)->pluck('subjects.id') ?? [])
                        ->when(static::classCurriculum($get('school_class_id')) === 'a_level', fn ($q) => $q->where('subjects.category', 'subsidiary')),
                )
                ->multiple()
                ->preload()
                ->visible(fn (Get $get): bool => in_array(static::classCurriculum($get('school_class_id')), ['o_level', 'a_level', 'primary'], true))
                ->helperText('Only these students appear on the mark sheet for an elective.'),

            // Day / Boarding. Required on purpose: fees tied
            // to a residency would silently miss a student
            // who has none, and under-billing is discovered
            // late and awkwardly.
            Select::make('residency_type_id')
                ->label('Residency')
                ->relationship(
                    'residencyType',
                    'name',
                    fn (Builder $query) => static::scopeToSchool($query)
                        ->where('is_active', true),
                )
                ->searchable()
                ->preload()
                ->placeholder('Select residency')
                ->required()
                ->helperText('Day, boarding, and so on. Decides which fees this student pays.'),

            Select::make('house_id')
                ->label('House')
                ->relationship(
                    'house',
                    'name',
                    modifyQueryUsing: fn (Builder $query, ?Student $record) => $query
                        ->where('school_id', auth()->user()?->school_id)
                        ->where('is_active', true)
                        ->where(function (Builder $query) use ($record) {
                            $query
                                ->whereNull('capacity')
                                ->orWhereRaw(
                                    'capacity > (select count(*) from students where students.house_id = houses.id)'
                                )
                                ->when(
                                    $record?->house_id,
                                    fn (Builder $houseQuery, $houseId) => $houseQuery->orWhere('houses.id', $houseId),
                                );
                        }),
                )
                ->searchable()
                ->preload()
                ->placeholder('Select house')
                ->helperText('Full houses are hidden automatically.'),
        ];
    }

    /**
     * Guardian and direct student contact details.
     *
     * @return array<int, Component>
     */
    public static function contactFields(): array
    {
        return [
            Select::make('guardian_id')
                ->label('Parent / Guardian')
                ->relationship(
                    'guardian',
                    'name',
                    fn (Builder $query) => static::scopeToSchool($query),
                )
                ->searchable()
                ->preload()
                ->placeholder('Select or add a parent / guardian')
                ->columnSpanFull()
                ->createOptionForm([
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])
                        ->schema([
                            TextInput::make('name')
                                ->label('Full name')
                                ->required()
                                ->maxLength(150),

                            TextInput::make('phone')
                                ->label('Phone number')
                                ->tel()
                                ->required()
                                ->maxLength(30),

                            Select::make('relationship')
                                ->label('Relationship')
                                ->options(Guardian::RELATIONSHIPS)
                                ->default('guardian')
                                ->native(false)
                                ->required(),

                            EmailCheck::apply(TextInput::make('email'))
                                ->label('Email address')
                                ->email()
                                ->maxLength(150),
                        ]),
                ])
                ->createOptionUsing(function (array $data) {
                    $data['school_id'] = auth()->user()?->school_id;

                    return Guardian::create($data)->getKey();
                }),

            TextInput::make('phone')
                ->label('Student phone')
                ->tel()
                ->placeholder('Optional')
                ->maxLength(30),

            EmailCheck::apply(TextInput::make('email'))
                ->label('Student email')
                ->email()
                ->placeholder('Optional')
                ->maxLength(150),
        ];
    }

    /**
     * Address and safety/medical notes — all optional.
     *
     * @return array<int, Component>
     */
    public static function welfareFields(): array
    {
        return [
            Textarea::make('address')
                ->label('Residential address')
                ->placeholder('Enter the student\'s home address')
                ->rows(4),

            Textarea::make('medical_notes')
                ->label('Medical / emergency notes')
                ->placeholder('Allergies, conditions, medication, emergency information...')
                ->helperText('Only record information staff may need for student safety or emergencies.')
                ->rows(4),
        ];
    }

    /**
     * The registration number: suggested automatically, unique in the school.
     */
    public static function admissionNumberField(): TextInput
    {
        return TextInput::make('admission_no')
            ->label('Registration No.')
            ->required()
            ->default(fn () => static::nextAdmissionNumber())
            ->helperText('Auto-generated — change only if needed.')
            ->rule(fn (Get $get, ?Student $record) => function ($attribute, $value, $fail) use ($get, $record) {
                $user = auth()->user();

                $schoolId = $user?->hasRole('Super Admin')
                    ? $get('school_id')
                    : $user?->school_id;

                if (! $schoolId) {
                    return;
                }

                $exists = Student::query()
                    ->where('school_id', $schoolId)
                    ->where('admission_no', $value)
                    ->when(
                        $record,
                        fn (Builder $query) => $query->whereKeyNot($record->getKey()),
                    )
                    ->exists();

                if ($exists) {
                    $fail('This registration number is already used at this school.');
                }
            });
    }

    /**
     * Quick admission: only what fees, marks and parents need, so a learner
     * is admitted at the counter in under a minute. Everything else (photo,
     * LIN, date of birth, house, address...) is added later on the
     * learner's profile, which says what is still missing.
     *
     * @return array<int, Component>
     */
    public static function quickFields(): array
    {
        $pick = fn (array $fields, array $names): array => array_values(array_filter(
            $fields,
            fn (Component $field): bool => $field instanceof Field && in_array($field->getName(), $names, true),
        ));

        return [
            Section::make('Learner')
                ->icon('heroicon-o-user')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('first_name')
                        ->label('First name')
                        ->required()
                        ->maxLength(100)
                        ->autofocus(),

                    TextInput::make('last_name')
                        ->label('Last name')
                        ->required()
                        ->maxLength(100),

                    ToggleButtons::make('gender')
                        ->label('Sex')
                        ->options(Student::GENDERS)
                        ->inline(),

                    static::admissionNumberField(),

                    ...$pick(static::enrollmentFields(), ['school_class_id', 'section_id', 'combination_id', 'residency_type_id']),
                ]),

            Section::make('Parent / guardian')
                ->icon('heroicon-o-user-group')
                ->description('Their phone receives receipts, fee reminders and the learner\'s fees page.')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    static::parentSearchField()
                        ->columnSpanFull(),

                    TextInput::make('new_guardian_name')
                        ->label('Parent\'s full name')
                        ->required(fn (Get $get): bool => blank($get('guardian_id')))
                        ->visible(fn (Get $get): bool => blank($get('guardian_id')))
                        ->maxLength(150),

                    TextInput::make('new_guardian_phone')
                        ->label('Parent\'s phone')
                        ->tel()
                        ->placeholder('07XX XXX XXX')
                        ->required(fn (Get $get): bool => blank($get('guardian_id')))
                        ->visible(fn (Get $get): bool => blank($get('guardian_id')))
                        ->maxLength(30),

                    ToggleButtons::make('new_guardian_relationship')
                        ->label('Relationship')
                        ->options(Guardian::RELATIONSHIPS)
                        ->default('guardian')
                        ->inline()
                        ->visible(fn (Get $get): bool => blank($get('guardian_id')))
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * One search for the family: an existing parent by name or phone, or a
     * brother or sister already at the school (brings in their parent).
     */
    public static function parentSearchField(): Select
    {
        return Select::make('guardian_id')
            ->label('Existing parent or a brother / sister at the school')
            ->placeholder('Search a parent\'s name or phone, or a sibling\'s name')
            ->helperText('Leave empty for a new family and fill in the parent below.')
            ->searchable()
            ->live()
            ->getSearchResultsUsing(fn (string $search): array => static::searchFamilies($search))
            ->getOptionLabelUsing(fn ($value): ?string => static::familyLabel(Guardian::with('students.schoolClass')->whereKey($value)->first()));
    }

    /**
     * @return array<int, string> guardian id => label
     */
    public static function searchFamilies(string $search): array
    {
        $search = trim($search);
        $digits = preg_replace('/\D/', '', $search);

        $user = auth()->user();

        return Guardian::query()
            ->when($user && ! $user->hasRole('Super Admin'), fn (Builder $q) => $q->where('school_id', $user->school_id))
            ->with('students.schoolClass')
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->when(strlen((string) $digits) >= 4, fn (Builder $q) => $q
                    ->orWhere('phone', 'like', "%{$digits}%")
                    ->orWhere('alt_phone', 'like', "%{$digits}%"))
                ->orWhereHas('students', fn (Builder $q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('admission_no', $search)))
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Guardian $guardian): array => [$guardian->getKey() => (string) static::familyLabel($guardian)])
            ->all();
    }

    /**
     * "Sarah Nakato · 0772 555666 — parent of Aisha Nakato (P.4)".
     */
    public static function familyLabel(?Guardian $guardian): ?string
    {
        if (! $guardian) {
            return null;
        }

        $children = $guardian->students
            ->map(fn (Student $child): string => trim($child->name.($child->schoolClass ? " ({$child->schoolClass->name})" : '')))
            ->take(3)
            ->implode(', ');

        return trim("{$guardian->name} · {$guardian->phone}".($children !== '' ? " — parent of {$children}" : ''));
    }

    /**
     * Before a quick admission is saved: use the family chosen, or make the
     * new parent -- reusing one already on record with the same phone so a
     * family is never entered twice.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveQuickGuardian(array $data, int $schoolId): array
    {
        if (blank($data['guardian_id'] ?? null) && filled($data['new_guardian_name'] ?? null)) {
            $phone = trim((string) ($data['new_guardian_phone'] ?? ''));
            $digits = preg_replace('/\D/', '', $phone);

            $existing = strlen((string) $digits) >= 9
                ? Guardian::where('school_id', $schoolId)
                    ->get(['id', 'phone'])
                    ->first(fn (Guardian $g): bool => substr((string) preg_replace('/\D/', '', (string) $g->phone), -9) === substr((string) $digits, -9))
                : null;

            $data['guardian_id'] = $existing?->getKey() ?? Guardian::create([
                'school_id' => $schoolId,
                'name' => trim((string) $data['new_guardian_name']),
                'phone' => $phone,
                'relationship' => $data['new_guardian_relationship'] ?? 'guardian',
            ])->getKey();
        }

        unset($data['new_guardian_name'], $data['new_guardian_phone'], $data['new_guardian_relationship']);

        return $data;
    }

    protected static function scopeToSchool(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }

    protected static function nextAdmissionNumber(): string
    {
        $schoolId = auth()->user()?->school_id;

        if (! $schoolId) {
            return '';
        }

        $last = Student::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->value('admission_no');

        $next = ((int) preg_replace('/\D/', '', (string) $last)) + 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * The curriculum of the chosen class (primary, o_level, a_level...).
     */
    protected static function classCurriculum($classId): ?string
    {
        return $classId ? SchoolClass::with('classLevel')->find($classId)?->curriculum() : null;
    }
}
