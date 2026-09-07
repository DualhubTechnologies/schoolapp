<?php

namespace App\Filament\Resources\DeductionTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;

class DeductionTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->placeholder('e.g. NSSF Employee, PAYE, Staff Loan, Welfare'),
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),
                Toggle::make('is_statutory')
                    ->helperText('Statutory deductions (NSSF, PAYE) are calculated automatically during payroll generation.'),
                Select::make('calculation_method')
                    ->options(['fixed' => 'Fixed Amount', 'percentage' => 'Percentage of Gross'])
                    ->default('fixed')
                    ->required()
                    ->live(),
                TextInput::make('default_rate')
                    ->numeric()
                    ->suffix(fn (Get $get) => $get('calculation_method') === 'percentage' ? '%' : null)
                    ->helperText('Default rate for this deduction type. Can be overridden per staff member.')
                    ->visible(fn (Get $get) => $get('calculation_method') === 'percentage'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}