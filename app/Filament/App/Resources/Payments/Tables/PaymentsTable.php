<?php

namespace App\Filament\App\Resources\Payments\Tables;

use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\StudentAccount;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Support\OwnSchool;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('receipt_seq', 'desc')
            ->columns([
                TextColumn::make('receipt_no')
                    ->label('Receipt')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('receipt_seq', $direction))
                    ->color(fn (StudentPayment $record) => $record->isVoided() ? 'gray' : null)
                    ->description(fn (StudentPayment $record) => $record->isVoided() ? 'VOID — '.$record->void_reason : null),

                TextColumn::make('paid_on')
                    ->label('Date')
                    ->date('j M Y')
                    ->sortable(),

                TextColumn::make('student.name')
                    ->label('Student')
                    ->description(fn (StudentPayment $record) => $record->student?->admission_no)
                    ->searchable(['name', 'admission_no']),

                TextColumn::make('student.schoolClass.name')
                    ->label('Class')
                    ->badge(),

                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => StudentPayment::METHODS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'cash' => 'success',
                        'mobile_money' => 'warning',
                        'bank' => 'info',
                        'schoolpay' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('reference')
                    ->label('Txn / slip ref.')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('paid_by')
                    ->label('Paid by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recorded_by')
                    ->label('Received by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Amount (UGX)')
                    ->numeric()
                    ->weight(FontWeight::Bold)
                    ->alignEnd()
                    ->color(fn (StudentPayment $record) => $record->isVoided() ? 'gray' : 'success')
                    ->extraAttributes(fn (StudentPayment $record) => $record->isVoided() ? ['style' => 'text-decoration: line-through'] : [])
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->numeric()
                            // Voided receipts never count towards a total.
                            ->query(fn ($query) => $query->whereNull('voided_at')),
                    ),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('From')->native(false)->displayFormat('j M Y'),
                        DatePicker::make('until')->label('To')->native(false)->displayFormat('j M Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('paid_on', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('paid_on', '<=', $d)))
                    ->indicateUsing(function (array $data): ?string {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        return match (true) {
                            $from && $until && $from === $until => 'On '.Carbon::parse($from)->format('j M Y'),
                            $from && $until => Carbon::parse($from)->format('j M').' – '.Carbon::parse($until)->format('j M Y'),
                            (bool) $from => 'From '.Carbon::parse($from)->format('j M Y'),
                            (bool) $until => 'Until '.Carbon::parse($until)->format('j M Y'),
                            default => null,
                        };
                    }),

                SelectFilter::make('method')
                    ->options(StudentPayment::METHODS),

                SelectFilter::make('term_id')
                    ->label('Term')
                    ->options(fn () => Term::where('school_id', auth()->user()?->school_id)
                        ->with('academicYear')
                        ->get()
                        ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                        ->all()),

                SelectFilter::make('class')
                    ->label('Class')
                    ->relationship('student.schoolClass', 'name', fn (Builder $query) => OwnSchool::scope($query))
                    ->preload(),

                SelectFilter::make('status')
                    ->options(['valid' => 'Valid receipts', 'void' => 'Voided receipts'])
                    ->default('valid')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'valid' => $query->whereNull('voided_at'),
                        'void' => $query->whereNotNull('voided_at'),
                        default => $query,
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('print')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (StudentPayment $record) => ReceivePayment::receiptUrl($record), shouldOpenInNewTab: true),

                ActionGroup::make([
                    Action::make('account')
                        ->label('Student account')
                        ->icon('heroicon-o-book-open')
                        ->url(fn (StudentPayment $record) => StudentAccount::getUrl(['student' => $record->student_id])),

                    Action::make('void')
                        ->label('Void receipt')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (StudentPayment $record) => ! $record->isVoided())
                        ->requiresConfirmation()
                        ->modalHeading(fn (StudentPayment $record) => "Void receipt {$record->receipt_no}?")
                        ->modalDescription('The receipt stays in the book marked VOID, and the amount is taken off the student\'s payments. This cannot be undone — record a new payment if needed.')
                        ->schema([
                            TextInput::make('reason')
                                ->label('Reason')
                                ->placeholder('e.g. Wrong student, amount entered wrongly, cheque bounced')
                                ->required()
                                ->maxLength(200),
                        ])
                        ->action(function (StudentPayment $record, array $data) {
                            $record->void($data['reason']);

                            Notification::make()
                                ->title("Receipt {$record->receipt_no} voided")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No payments found')
            ->emptyStateDescription('Payments you receive appear here as numbered receipts.')
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
