<?php

namespace App\Filament\App\Resources\Staff\Schemas;

use App\Models\Staff;
use App\Support\EmailCheck;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A staff member's record. Pay itself -- salary, allowances, deductions,
 * bank details -- is set in the tabs below the form once the record is
 * saved.
 */
class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->components([
                Section::make('Personal details')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        Select::make('gender')
                            ->options(Staff::GENDERS)
                            ->native(false),
                        TextInput::make('nin')
                            ->label('National ID (NIN)')
                            ->placeholder('e.g. CM90012345ABCD')
                            ->maxLength(20),
                        TextInput::make('phone')
                            ->tel()
                            ->placeholder('e.g. 0772 123456'),
                        EmailCheck::apply(TextInput::make('email'))
                            ->label('Email address')
                            ->email(),
                    ]),

                Section::make('Employment')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        Select::make('school_id')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->visible(fn () => auth()->user()->hasRole('Super Admin'))
                            ->required()
                            ->columnSpanFull(),
                        ToggleButtons::make('category')
                            ->label('Staff category')
                            ->options(Staff::CATEGORIES)
                            ->icons([
                                'teaching' => 'heroicon-o-academic-cap',
                                'non_teaching' => 'heroicon-o-wrench-screwdriver',
                            ])
                            ->colors(['teaching' => 'primary', 'non_teaching' => 'gray'])
                            ->default('teaching')
                            ->inline()
                            ->required()
                            ->helperText('Only teaching staff can be assigned subjects to teach.')
                            ->columnSpanFull(),
                        TextInput::make('initials')
                            ->label('Initials on report cards')
                            ->placeholder('e.g. B.K.')
                            ->maxLength(10)
                            ->helperText('Leave blank to use the first letter of each name.'),
                        TextInput::make('staff_no')
                            ->label('Staff number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Another staff member already has this number.']),
                        Select::make('employment_type')
                            ->label('Employment type')
                            ->options(Staff::EMPLOYMENT_TYPES)
                            ->default('permanent')
                            ->native(false)
                            ->required(),
                        TextInput::make('position')
                            ->placeholder('e.g. Teacher, Bursar, Matron, Driver')
                            ->required(),
                        TextInput::make('department')
                            ->placeholder('e.g. Sciences, Administration'),
                        DatePicker::make('employment_date')
                            ->label('Date employed')
                            ->native(false)
                            ->displayFormat('j M Y')
                            ->required(),
                        Select::make('status')
                            ->options(Staff::STATUSES)
                            ->default('active')
                            ->native(false)
                            ->required()
                            ->helperText('Only active staff are included in payroll.'),
                    ]),

                Section::make('Tax & NSSF')
                    ->icon('heroicon-o-building-library')
                    ->description('Needed for the PAYE return to URA and the NSSF schedule.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('tin_number')
                            ->label('TIN')
                            ->placeholder('e.g. 1001234567')
                            ->maxLength(20),
                        TextInput::make('nssf_number')
                            ->label('NSSF number')
                            ->placeholder('e.g. NF12345678901')
                            ->maxLength(30),
                        Toggle::make('pays_nssf')
                            ->label('Contributes to NSSF')
                            ->helperText('5% from salary, plus 10% paid by the school.')
                            ->default(true),
                        Toggle::make('pays_lst')
                            ->label('Pays Local Service Tax')
                            ->helperText('Deducted July–October, by pay band.')
                            ->default(true),
                    ]),
            ]);
    }
}
