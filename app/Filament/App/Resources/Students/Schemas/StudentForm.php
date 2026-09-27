<?php

namespace App\Filament\App\Resources\Students\Schemas;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\EmailCheck;
use App\Support\ImageShrinker;
use App\Support\PrivateFiles;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make([
                    'default' => 1,
                    'xl' => 12,
                ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'sh-student-form sh-student-layout',
                    ])
                    ->schema([
                        /*
                         |----------------------------------------------------------
                         | LEFT: Student identity
                         |----------------------------------------------------------
                         | This intentionally stays tall. The two sections on the
                         | right stack beside it, so the first row is fully used.
                         */
                        Section::make('Student Identity')
                            ->description('Personal details, identification and student photo.')
                            ->extraAttributes([
                                'class' => 'sh-student-card sh-student-card--identity sh-student-identity',
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'xl' => 6,
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 6,
                            ])
                            ->schema(static::identityFields()),

                        /*
                         |----------------------------------------------------------
                         | RIGHT: two stacked cards
                         |----------------------------------------------------------
                         */
                        Group::make()
                            ->columnSpan([
                                'default' => 1,
                                'xl' => 6,
                            ])
                            ->extraAttributes([
                                'class' => 'sh-student-right-stack',
                            ])
                            ->schema([
                                Section::make('Class and Enrollment')
                                    ->description('Academic placement and school grouping.')
                                    ->extraAttributes([
                                        'class' => 'sh-student-card sh-student-card--enrollment',
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ])
                                    ->schema(static::enrollmentFields()),

                                Section::make('Parent and Contact Details')
                                    ->description('Guardian and direct student contact information.')
                                    ->extraAttributes([
                                        'class' => 'sh-student-card sh-student-card--contact',
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ])
                                    ->schema(static::contactFields()),
                            ]),

                        /*
                         |----------------------------------------------------------
                         | BOTTOM: full width
                         |----------------------------------------------------------
                         */
                        Section::make('Address and Student Welfare')
                            ->description('Home address and information staff may need for student safety.')
                            ->extraAttributes([
                                'class' => 'sh-student-card sh-student-card--welfare',
                            ])
                            ->columnSpanFull()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema(static::welfareFields()),
                    ]),
            ]);
    }

    /**
     * Photo, name, identifiers. Used by the single-page edit form and by
     * the first step of the admission wizard.
     *
     * @return array<int, Component>
     */
    public static function identityFields(): array
    {
        return [
            ImageShrinker::noBrowserResize(FileUpload::make('photo')
                ->label('Student photo')
                ->image()
                ->avatar()
                ->imageEditor()
                ->circleCropper()
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
                ->columnSpan([
                    'default' => 1,
                    'md' => 2,
                ])),

            Grid::make([
                'default' => 1,
                'sm' => 2,
            ])
                ->columnSpan([
                    'default' => 1,
                    'md' => 4,
                ])
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

                    TextInput::make('admission_no')
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
                        }),

                    Select::make('gender')
                        ->label('Sex')
                        ->options(Student::GENDERS)
                        ->placeholder('Select sex')
                        ->native(false),

                    DatePicker::make('date_of_birth')
                        ->label('Birth date')
                        ->maxDate(now()),

                    DatePicker::make('admission_date')
                        ->label('Admission date')
                        ->default(now()),

                    TextInput::make('lin')
                        ->hintIcon('heroicon-m-question-mark-circle', tooltip: "Learner Identification Number from the Ministry of Education's EMIS system.")
                        ->label('LIN')
                        ->placeholder('Learner Identification Number')
                        ->maxLength(100),

                    TextInput::make('nin')
                        ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'National Identification Number, if the learner has a national ID.')
                        ->label('National ID')
                        ->maxLength(100),

                    Select::make('status')
                        ->label('Status')
                        ->options(Student::STATUSES)
                        ->default('active')
                        ->native(false)
                        ->required(),

                    // Only the platform owner picks a school; everyone else's
                    // students belong to their own school (set on create).
                    Select::make('school_id')
                        ->label('School')
                        ->relationship('school', 'name')
                        ->default(fn () => auth()->user()?->school_id)
                        ->visible(fn () => auth()->user()?->hasRole('Super Admin') ?? false)
                        ->required(),
                ]),
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
