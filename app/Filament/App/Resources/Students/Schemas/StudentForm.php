<?php

namespace App\Filament\App\Resources\Students\Schemas;

use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Full name')
                            ->required(),
                        TextInput::make('admission_no')
                            ->label('Admission number')
                            ->required()
                            ->default(fn () => static::nextAdmissionNumber())
                            ->helperText('Auto-suggested. Edit if your school uses a different format.')
                            ->rule(fn ($record) => function ($attribute, $value, $fail) use ($record) {
                                $schoolId = auth()->user()->school_id;

                                $exists = Student::where('school_id', $schoolId)
                                    ->where('admission_no', $value)
                                    ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($exists) {
                                    $fail('This admission number is already used at your school.');
                                }
                            }),
                        Select::make('school_id')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                            ->dehydrated()
                            ->required(),
                        Select::make('gender')
                            ->options(Student::GENDERS),
                        DatePicker::make('date_of_birth')
                            ->label('Date of birth')
                            ->maxDate(now()),
                        DatePicker::make('admission_date')
                            ->default(now()),
                        FileUpload::make('photo')
                            ->image()
                            ->imageEditor()
                            ->directory('students')
                            ->columnSpanFull(),
                    ]),

                Section::make('Placement')
                    ->columns(2)
                    ->schema([
                        Select::make('school_class_id')
                            ->label('Class')
                            ->relationship('schoolClass', 'name', fn (Builder $query) => static::scopeToSchool($query))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('section_id', null))
                            ->required(),
                        Select::make('section_id')
                            ->label('Section')
                            ->relationship(
                                'section',
                                'name',
                                fn (Builder $query, Get $get) => static::scopeToSchool($query)
                                    ->where('school_class_id', $get('school_class_id') ?? 0),
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Pick a class first.'),
                        Select::make('status')
                            ->options(Student::STATUSES)
                            ->default('active')
                            ->required(),
                    ]),

                Section::make('Contact & Guardian')
                    ->columns(2)
                    ->schema([
                        Select::make('guardian_id')
                            ->label('Guardian / Parent')
                            ->relationship('guardian', 'name', fn (Builder $query) => static::scopeToSchool($query))
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                                TextInput::make('phone')->tel()->required(),
                                Select::make('relationship')
                                    ->options(\App\Models\Guardian::RELATIONSHIPS)
                                    ->default('guardian')
                                    ->required(),
                                TextInput::make('email')->email(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['school_id'] = auth()->user()->school_id;

                                return \App\Models\Guardian::create($data)->getKey();
                            }),
                            Select::make('house_id')
                                ->label('House')
                                ->relationship(
                                    'house',
                                    'name',
                                    modifyQueryUsing: fn ($query) => $query
                                        ->where('school_id', auth()->user()->school_id)
                                        ->where('is_active', true)
                                )
                                ->searchable()
                                ->preload(),
                        TextInput::make('phone')
                            ->label('Student phone')
                            ->tel(),
                        TextInput::make('email')
                            ->label('Student email')
                            ->email(),
                        Textarea::make('address')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('medical_notes')
                            ->label('Medical notes')
                            ->helperText('Allergies, conditions, or anything staff should know in an emergency.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
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
        $schoolId = auth()->user()->school_id;

        if (! $schoolId) {
            return '';
        }

        $last = Student::where('school_id', $schoolId)
            ->orderByDesc('id')
            ->value('admission_no');

        $next = ((int) preg_replace('/\D/', '', (string) $last)) + 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}