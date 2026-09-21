<?php

namespace App\Filament\App\Resources\AcademicYears\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Academic year')
                    ->description('The school year that terms belong to.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Year name')
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('school_id', $get('school_id') ?? auth()->user()?->school_id))
                            ->validationMessages(['unique' => 'An academic year with this name already exists.'])
                            ->maxLength(50)
                            ->placeholder('e.g. 2026 or 2026/2027')
                            ->helperText('Use whatever format your school uses.'),

                        // Same pattern as HouseForm: defaulted to the signed-in
                        // user's school and locked unless they're Super Admin.
                        Select::make('school_id')
                            ->label('School')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                            ->dehydrated()
                            ->required(),

                        DatePicker::make('start_date')
                            ->label('Starts on'),

                        DatePicker::make('end_date')
                            ->label('Ends on')
                            ->afterOrEqual('start_date'),

                        Toggle::make('is_current')
                            ->label('Current academic year')
                            ->helperText('Only one year can be current. Turning this on switches it off everywhere else.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
