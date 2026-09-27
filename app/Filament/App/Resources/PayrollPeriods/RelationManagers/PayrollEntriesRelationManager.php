<?php

namespace App\Filament\App\Resources\PayrollPeriods\RelationManagers;

use App\Services\Payroll\PayrollService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Payslips';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn ($record): string => $record->staff?->name ?? 'Payroll entry')
            ->columns([
                TextColumn::make('staff.name')
                    ->label('Staff member')
                    ->description(fn ($record) => collect([$record->staff?->staff_no, $record->staff?->position])->filter()->implode(' · '))
                    ->searchable(['name', 'staff_no'])
                    ->sortable(),
                TextColumn::make('base_salary')
                    ->label('Basic')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('total_allowances')
                    ->label('Allowances')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('gross_pay')
                    ->label('Gross')
                    ->numeric()
                    ->alignEnd()
                    ->summarize(Sum::make()->label('')->numeric()),
                TextColumn::make('paye')
                    ->label('PAYE')
                    ->numeric()
                    ->alignEnd()
                    ->summarize(Sum::make()->label('')->numeric()),
                TextColumn::make('nssf_employee')
                    ->label('NSSF 5%')
                    ->numeric()
                    ->alignEnd()
                    ->summarize(Sum::make()->label('')->numeric()),
                TextColumn::make('lst')
                    ->label('LST')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('total_deductions')
                    ->label('Other ded.')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('arrears_amount')
                    ->label('Arrears')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('net_pay')
                    ->label('Net pay')
                    ->numeric()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Sum::make()->label('')->numeric()),
                TextColumn::make('nssf_employer')
                    ->label('Employer NSSF 10%')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'included' => 'success',
                        'excluded' => 'danger',
                        default => 'warning',
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
                            ->title($record->staff->name.' excluded from payroll')
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
                            ->title($record->staff->name.' re-included in payroll')
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
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    protected static function recalculatePeriodTotals($period): void
    {
        app(PayrollService::class)->refreshTotals($period);
    }
}
