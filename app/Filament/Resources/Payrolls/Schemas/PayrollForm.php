<?php

namespace App\Filament\Resources\Payrolls\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PayrollForm
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
                TextInput::make('period')
                    ->placeholder('e.g. September 2026')
                    ->required(),
                TextInput::make('base_salary')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::updateNetPay($get, $set);
                    }),
                TextInput::make('allowances')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::updateNetPay($get, $set);
                    }),
                TextInput::make('deductions')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::updateNetPay($get, $set);
                    }),
                TextInput::make('net_pay')
                    ->numeric()
                    ->required()
                    ->readOnly()
                    ->helperText('Calculated automatically: base salary + allowances − deductions.'),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'paid' => 'Paid'])
                    ->default('pending')
                    ->required()
                    ->live(),
                DatePicker::make('paid_on')
                    ->visible(fn (Get $get) => $get('status') === 'paid'),
            ]);
    }

    protected static function updateNetPay(Get $get, Set $set): void
    {
        $base = (float) ($get('base_salary') ?? 0);
        $allowances = (float) ($get('allowances') ?? 0);
        $deductions = (float) ($get('deductions') ?? 0);

        $set('net_pay', number_format($base + $allowances - $deductions, 2, '.', ''));
    }
}
