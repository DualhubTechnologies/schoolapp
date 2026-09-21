<?php

namespace App\Filament\App\Resources\PayrollPeriods\Tables;

use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * One row per month's payroll. Everything else happens on the run's own
 * page -- click a row to open it.
 */
class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('year')->orderByDesc('month'))
            ->recordUrl(fn (PayrollPeriod $record) => PayrollPeriodResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(fn (PayrollPeriod $record) => $record->period_label)
                    ->weight(FontWeight::SemiBold)
                    ->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('year', $direction)->orderBy('month', $direction)),
                TextColumn::make('school.name')
                    ->label('School')
                    ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                TextColumn::make('staff_count')
                    ->label('Staff')
                    ->alignCenter(),
                TextColumn::make('total_gross')
                    ->label('Gross')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('total_paye')
                    ->label('PAYE')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('nssf')
                    ->label('NSSF (15%)')
                    ->state(fn (PayrollPeriod $record) => (float) $record->total_nssf_employee + (float) $record->total_employer_nssf)
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('total_net')
                    ->label('Net pay (UGX)')
                    ->numeric()
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PayrollPeriod::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('payment_date')
                    ->label('Paid on')
                    ->date('j M Y')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(PayrollPeriod::STATUSES),
                SelectFilter::make('year')
                    ->options(fn () => PayrollPeriod::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
            ])
            ->emptyStateHeading('No payroll yet')
            ->emptyStateDescription('Set up staff salaries under Staff, then start this month\'s payroll.')
            ->emptyStateIcon('heroicon-o-wallet');
    }
}
