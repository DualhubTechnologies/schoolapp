<?php

namespace App\Filament\Resources\PayrollPeriods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayrollPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),
                Select::make('month')
                    ->options([
                        1 => 'January', 2 => 'February', 3 => 'March',
                        4 => 'April', 5 => 'May', 6 => 'June',
                        7 => 'July', 8 => 'August', 9 => 'September',
                        10 => 'October', 11 => 'November', 12 => 'December',
                    ])
                    ->default(now()->month)
                    ->required(),
                TextInput::make('year')
                    ->numeric()
                    ->default(now()->year)
                    ->required()
                    ->minValue(2020)
                    ->maxValue(2050),
                Textarea::make('notes')
                    ->placeholder('Optional notes for this payroll run'),
            ]);
    }
}