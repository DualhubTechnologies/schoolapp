<?php

namespace App\Filament\App\Resources\DeductionTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class DeductionTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('school_id', $get('school_id') ?? auth()->user()?->school_id))
                    ->validationMessages(['unique' => 'A deduction type with this name already exists.'])
                    ->placeholder('e.g. Staff loan, SACCO, Welfare')
                    ->helperText('NSSF, PAYE and Local Service Tax are worked out automatically. Do not add them here.'),
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),
                Toggle::make('is_statutory')
                    ->label('Statutory (ignored by payroll)')
                    ->helperText('Payroll already calculates NSSF, PAYE and LST. A type marked statutory is never deducted, so it cannot be charged twice.'),
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