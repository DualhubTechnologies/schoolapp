<?php

namespace App\Filament\App\Resources\AllowanceTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AllowanceTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('school_id', $get('school_id') ?? auth()->user()?->school_id))
                    ->validationMessages(['unique' => 'An allowance type with this name already exists.'])
                    ->placeholder('e.g. Housing, Transport, Lunch, Medical'),
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),
                Toggle::make('is_taxable')
                    ->default(true)
                    ->helperText('Taxable allowances are included in PAYE calculation.'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
