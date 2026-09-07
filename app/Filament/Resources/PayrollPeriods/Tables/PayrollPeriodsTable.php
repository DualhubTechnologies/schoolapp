<?php

namespace App\Filament\Resources\PayrollPeriods\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollPeriodsTable
{
    private const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September',
        10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('month')
                    ->label('Period')
                    ->formatStateUsing(fn ($record) => (self::MONTHS[$record->month] ?? 'Unknown') . ' ' . $record->year)
                    ->sortable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable(),
                TextColumn::make('staff_count')
                    ->label('Staff')
                    ->sortable(),
                TextColumn::make('total_gross')
                    ->label('Gross')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('total_deductions')
                    ->label('Deductions')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('total_statutory')
                    ->label('Statutory')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('total_net')
                    ->label('Net pay')
                    ->money('UGX')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('total_employer_nssf')
                    ->label('Employer NSSF')
                    ->money('UGX')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'warning',
                        'paid' => 'success',
                    }),
                TextColumn::make('paid_at')
                    ->label('Paid on')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                    ]),
            ])
            ->recordActions([
                Action::make('generate')
                    ->label('Generate')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'draft' && $record->staff_count === 0)
                    ->requiresConfirmation()
                    ->modalHeading('Generate payroll')
                    ->modalDescription(fn ($record) => "This will calculate salary, allowances, deductions, NSSF (5% + 10%), and PAYE for all active staff in {$record->period_label}. Continue?")
                    ->action(function ($record) {
                        $record->update(['generated_by' => auth()->id()]);
                        $count = $record->generateEntries();

                        Notification::make()
                            ->title("Payroll generated for {$count} staff members")
                            ->success()
                            ->send();
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'draft' && $record->staff_count > 0)
                    ->requiresConfirmation()
                    ->modalHeading('Approve payroll')
                    ->modalDescription(fn ($record) => "Approve {$record->period_label} payroll? Total net pay: UGX " . number_format($record->total_net, 0))
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Payroll approved')
                            ->success()
                            ->send();
                    }),
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'approved')
                    ->requiresConfirmation()
                    ->modalHeading('Mark payroll as paid')
                    ->modalDescription(fn ($record) => "Confirm that {$record->period_label} payroll (UGX " . number_format($record->total_net, 0) . " net) has been disbursed to all {$record->staff_count} staff members?")
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Payroll marked as paid')
                            ->success()
                            ->send();
                    }),
                Action::make('nssfPdf')
                    ->label('NSSF Schedule')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn ($record) => $record->staff_count > 0)
                    ->url(fn ($record) => route('nssf-schedule.pdf', $record), shouldOpenInNewTab: true),
                Action::make('nssfExcel')
                    ->label('NSSF Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('gray')
                    ->visible(fn ($record) => $record->staff_count > 0)
                    ->url(fn ($record) => route('nssf-schedule.excel', $record), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}