<?php

namespace App\Filament\App\Resources\FeeBalances\Tables;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Models\Student;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeeBalancesTable
{
    public static function configure(Table $table): Table
    {
        $charged = FeeBalanceResource::chargedSql();
        $paid = FeeBalanceResource::paidSql();

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('admission_no')
                    ->label('Adm. No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->searchable(),

                TextColumn::make('section.name')
                    ->label('Stream')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('residencyType.name')
                    ->label('Residency')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('total_charged')
                    ->label('Charged')
                    ->money('UGX')
                    ->sortable(),

                TextColumn::make('total_paid')
                    ->label('Paid')
                    ->money('UGX')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('balance_owing')
                    ->label('Balance')
                    ->money('UGX')
                    ->weight('bold')
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('percent_paid')
                    ->label('% paid')
                    ->state(fn (Student $record): string => $record->percentagePaid() . '%')
                    ->badge()
                    ->color(fn (Student $record): string => match (true) {
                        $record->percentagePaid() >= 100 => 'success',
                        $record->percentagePaid() >= 50 => 'warning',
                        $record->percentagePaid() > 0 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('balance_owing', 'desc')
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options([
                        'cleared' => 'Paid in full (owes nothing)',
                        'partial' => 'Partly paid',
                        'nothing' => 'Nothing paid',
                        'owing' => 'Has a balance',
                        'credit' => 'In credit (overpaid)',
                    ])
                    ->query(function (Builder $query, array $data) use ($charged, $paid): Builder {
                        return match ($data['value'] ?? null) {
                            'cleared' => $query->whereRaw("{$charged} > 0 and {$paid} >= {$charged}"),
                            'partial' => $query->whereRaw("{$paid} > 0 and {$paid} < {$charged}"),
                            'nothing' => $query->whereRaw("{$charged} > 0 and {$paid} = 0"),
                            'owing' => $query->whereRaw("{$charged} > {$paid}"),
                            'credit' => $query->whereRaw("{$paid} > {$charged}"),
                            default => $query,
                        };
                    }),

                SelectFilter::make('school_class_id')
                    ->label('Class')
                    ->relationship('schoolClass', 'name')
                    ->preload(),

                SelectFilter::make('residency_type_id')
                    ->label('Residency')
                    ->relationship('residencyType', 'name')
                    ->preload(),

                Filter::make('paid_at_least')
                    ->label('Paid at least')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Minimum paid')
                            ->numeric()
                            ->prefix('UGX')
                            ->placeholder('e.g. 200000'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['amount'] ?? null,
                            fn (Builder $q, $amount) => $q->whereRaw("{$paid} >= ?", [(float) $amount]),
                        )),

                Filter::make('percentage')
                    ->label('Percentage paid')
                    ->schema([
                        Select::make('operator')
                            ->label('Show students who have paid')
                            ->options([
                                'gte' => 'At least',
                                'lt' => 'Less than',
                            ])
                            ->default('gte')
                            ->native(false),
                        TextInput::make('percent')
                            ->label('Percent')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->placeholder('e.g. 50'),
                    ])
                    ->query(function (Builder $query, array $data) use ($charged, $paid): Builder {
                        $percent = $data['percent'] ?? null;

                        if ($percent === null || $percent === '') {
                            return $query;
                        }

                        $fraction = (float) $percent / 100;
                        $operator = ($data['operator'] ?? 'gte') === 'lt' ? '<' : '>=';

                        // Students with no charges are excluded — a
                        // percentage of nothing is not a meaningful answer.
                        return $query->whereRaw("{$charged} > 0")
                            ->whereRaw("({$paid} / {$charged}) {$operator} ?", [$fraction]);
                    }),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
