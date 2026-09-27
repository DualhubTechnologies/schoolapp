<?php

namespace App\Filament\App\Resources\FeeBalances\Tables;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\StudentAccount;
use App\Filament\Support\FeeReminderActions;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\FontWeight;
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

        // % paid from the totals already selected -- no extra queries per row.
        $percent = fn (Student $record): ?float => (float) $record->total_charged > 0
            ? round(min((float) $record->total_paid / (float) $record->total_charged * 100, 100))
            : null;

        return $table
            ->recordUrl(fn (Student $record) => StudentAccount::getUrl(['student' => $record->getKey()]))
            ->modifyQueryUsing(fn (Builder $query) => $query->with('guardian'))
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Student $record) => $record->admission_no)
                    ->searchable(['name', 'admission_no'])
                    ->sortable(),

                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Student $record) => $record->section
                        ? "{$state} · {$record->section->name}"
                        : (string) $state),

                TextColumn::make('residencyType.name')
                    ->label('Residency')
                    ->badge()
                    ->color(fn ($record) => $record->residencyType?->badgeColor() ?? 'gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('guardian.name')
                    ->label('Guardian')
                    ->placeholder('Not linked')
                    ->description(fn (Student $record) => $record->guardian?->phone)
                    ->toggleable(),

                TextColumn::make('total_charged')
                    ->label('Charged')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('total_paid')
                    ->label('Paid')
                    ->numeric()
                    ->alignEnd()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('balance_owing')
                    ->label('Balance (UGX)')
                    ->numeric()
                    ->alignEnd()
                    ->weight(FontWeight::Bold)
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => (float) $state < 0
                        ? number_format(abs((float) $state)).' CR'
                        : number_format((float) $state))
                    ->sortable(),

                TextColumn::make('percent_paid')
                    ->label('Paid')
                    ->state(fn (Student $record) => $percent($record) === null ? '—' : $percent($record).'%')
                    ->badge()
                    ->alignEnd()
                    ->color(fn (Student $record): string => match (true) {
                        $percent($record) === null => 'gray',
                        $percent($record) >= 100 => 'success',
                        $percent($record) >= 50 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->url(fn (Student $record) => ReceivePayment::getUrl(['student' => $record->getKey()])),

                ActionGroup::make([
                    Action::make('account')
                        ->label('Open account')
                        ->icon('heroicon-o-book-open')
                        ->url(fn (Student $record) => StudentAccount::getUrl(['student' => $record->getKey()])),
                    FeeReminderActions::smsSingle(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    FeeReminderActions::smsBulk(),
                    FeeReminderActions::lettersBulk(),
                ])->label('Reminders'),
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
