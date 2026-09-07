<?php

namespace App\Filament\Resources\PayrollPeriods\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Staff Payroll Entries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('staff_id')
            ->columns([
                TextColumn::make('staff.name')
                    ->label('Staff member')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('staff.staff_no')
                    ->label('Staff No.')
                    ->searchable(),
                TextColumn::make('base_salary')
                    ->label('Base')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('total_allowances')
                    ->label('Allowances')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('gross_pay')
                    ->label('Gross')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('nssf_employee')
                    ->label('NSSF (5%)')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('paye')
                    ->label('PAYE')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('total_deductions')
                    ->label('Other ded.')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('arrears_amount')
                    ->label('Arrears')
                    ->money('UGX')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('net_pay')
                    ->label('Net pay')
                    ->money('UGX')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('nssf_employer')
                    ->label('Employer NSSF')
                    ->money('UGX')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'included' => 'success',
                        'excluded' => 'danger',
                        'adjusted' => 'warning',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('exclude')
                    ->label('Exclude')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'included' && $record->payrollPeriod->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Exclude from payroll')
                    ->modalDescription(fn ($record) => "{$record->staff->name} will NOT be paid in this payroll run. Their entry will remain on record but excluded from all totals. Continue?")
                    ->action(function ($record) {
                        $record->update(['status' => 'excluded']);
                        self::recalculatePeriodTotals($record->payrollPeriod);

                        Notification::make()
                            ->title($record->staff->name . ' excluded from payroll')
                            ->warning()
                            ->send();
                    }),
                Action::make('include')
                    ->label('Re-include')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'excluded' && $record->payrollPeriod->status === 'draft')
                    ->requiresConfirmation()
                    ->modalDescription(fn ($record) => "Re-include {$record->staff->name} in this payroll run?")
                    ->action(function ($record) {
                        $record->update(['status' => 'included']);
                        self::recalculatePeriodTotals($record->payrollPeriod);

                        Notification::make()
                            ->title($record->staff->name . ' re-included in payroll')
                            ->success()
                            ->send();
                    }),
                Action::make('printPayslip')
                    ->label('Payslip')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'included')
                    ->url(fn ($record) => route('payslip.download', $record), shouldOpenInNewTab: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function recalculatePeriodTotals($period): void
    {
        $included = $period->entries()->where('status', 'included');

        $period->update([
            'total_gross' => $included->sum('gross_pay'),
            'total_allowances' => $included->sum('total_allowances'),
            'total_deductions' => $included->sum('total_deductions'),
            'total_statutory' => $included->sum('total_statutory'),
            'total_net' => $included->sum('net_pay'),
            'total_employer_nssf' => $included->sum('nssf_employer'),
            'staff_count' => $included->count(),
        ]);
    }
}