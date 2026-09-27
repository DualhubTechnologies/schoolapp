<?php

namespace App\Filament\App\Resources\SalaryArrears\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SalaryArrearForm
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
                Select::make('staff_id')
                    ->label('Staff member')
                    ->relationship(
                        'staff',
                        'name',
                        modifyQueryUsing: fn ($query) => auth()->user()->hasRole('Super Admin')
                            ? $query
                            : $query->where('school_id', auth()->user()->school_id)
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('amount')
                    ->label('Arrears amount')
                    ->required()
                    ->numeric()
                    ->prefix('UGX'),
                Textarea::make('reason')
                    ->required()
                    ->placeholder('e.g. Salary adjustment backdated to July, Missed overtime payment'),
                Select::make('month')
                    ->label('Arrears for month')
                    ->options([
                        1 => 'January', 2 => 'February', 3 => 'March',
                        4 => 'April', 5 => 'May', 6 => 'June',
                        7 => 'July', 8 => 'August', 9 => 'September',
                        10 => 'October', 11 => 'November', 12 => 'December',
                    ])
                    ->required(),
                TextInput::make('year')
                    ->label('Arrears for year')
                    ->required()
                    ->numeric()
                    ->default(now()->year)
                    ->minValue(2020)
                    ->maxValue(2050),
            ]);
    }
}
