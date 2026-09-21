<?php

namespace App\Filament\App\Resources\PayrollPeriods\Schemas;

use App\Models\PayrollPeriod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PayrollPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        // Suggest the month after the latest run (or this month).
        $latest = PayrollPeriod::where('school_id', auth()->user()?->school_id)
            ->orderByDesc('year')->orderByDesc('month')->first();
        $next = $latest ? $latest->monthStart()->addMonth() : now()->startOfMonth();

        return $schema
            ->components([
                Section::make('Which month?')
                    ->description('Payroll is calculated for every active staff member with a salary set, as soon as you save.')
                    ->columns(2)
                    ->schema([
                        Select::make('school_id')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->visible(fn () => auth()->user()->hasRole('Super Admin'))
                            ->required()
                            ->columnSpanFull(),

                        Select::make('month')
                            ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => now()->startOfYear()->month($m)->format('F')]))
                            ->default($next->month)
                            ->native(false)
                            ->required(),

                        TextInput::make('year')
                            ->numeric()
                            ->default($next->year)
                            ->minValue(2020)
                            ->maxValue(2050)
                            ->required()
                            ->unique(
                                table: 'payroll_periods',
                                column: 'year',
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                    ->where('month', $get('month'))
                                    ->where('school_id', $get('school_id') ?? auth()->user()?->school_id),
                            )
                            ->validationMessages(['unique' => 'A payroll for this month already exists.']),

                        Textarea::make('notes')
                            ->placeholder('Optional notes for this payroll run')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
