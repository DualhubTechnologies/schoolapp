<?php

namespace App\Filament\App\Resources\PayrollPeriods\Pages;

use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * One month's payroll: the totals, every payslip, and the buttons that
 * move the run from draft to approved to paid.
 */
class ViewPayrollPeriod extends ViewRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    public function getSubheading(): ?string
    {
        return match ($this->record->status) {
            'draft' => 'Draft: check the payslips below, fix any salary set-up and recalculate, then approve.',
            'approved' => 'Approved: figures are locked. Pay staff, then mark the payroll as paid.',
            'paid' => 'Paid' . ($this->record->payment_date ? ' on ' . $this->record->payment_date->format('j M Y') : '') . '. File PAYE, NSSF and LST using the documents menu.',
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        /** @var PayrollPeriod $period */
        $period = $this->record;

        return [
            Action::make('recalculate')
                ->label($period->staff_count ? 'Recalculate' : 'Calculate payroll')
                ->icon('heroicon-o-calculator')
                ->color($period->staff_count ? 'gray' : 'primary')
                ->visible(fn () => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('Calculate payroll')
                ->modalDescription('Works out every active staff member\'s pay from their current salary, allowances, deductions and approved arrears: NSSF, PAYE and LST included. Safe to repeat while the payroll is a draft.')
                ->action(function (PayrollService $payroll) {
                    $result = $payroll->generate($this->record);
                    $this->refreshFormData([]);

                    $notes = collect([
                        $result['skipped_no_salary'] ? count($result['skipped_no_salary']) . ' skipped with no salary set: ' . implode(', ', array_slice($result['skipped_no_salary'], 0, 5)) : null,
                        $result['missed_last_month'] ? "{$result['missed_last_month']} staff were not paid last month — pending arrears were created under Salary Arrears." : null,
                    ])->filter()->implode(' ');

                    Notification::make()
                        ->title("Payroll calculated for {$result['staff']} staff")
                        ->body($notes ?: null)
                        ->{$notes ? 'warning' : 'success'}()
                        ->persistent($notes !== '')
                        ->send();

                    // Reload so the totals and the payslips table below show
                    // the new figures.
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('warning')
                ->visible(fn () => $this->record->isDraft() && $this->record->staff_count > 0)
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Approve ' . $this->record->period_label . ' payroll?')
                ->modalDescription(fn () => 'Net pay UGX ' . number_format((float) $this->record->total_net) . ' for ' . $this->record->staff_count . ' staff. Approving locks the figures, records loan repayments and marks arrears as paid.')
                ->action(function (PayrollService $payroll) {
                    $payroll->approve($this->record);
                    $this->record->refresh();
                    Notification::make()->title('Payroll approved')->success()->send();
                }),

            Action::make('markPaid')
                ->label('Mark as paid')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->status === 'approved')
                ->modalHeading('Record salary payment')
                ->schema([
                    DatePicker::make('date')->label('Date paid')->native(false)->displayFormat('j M Y')->default(today())->maxDate(today())->required(),
                    Select::make('method')->label('Paid by')->options(PayrollPeriod::PAYMENT_METHODS)->default('bank')->native(false)->required(),
                    TextInput::make('reference')->label('Reference')->placeholder('e.g. bank batch number'),
                ])
                ->action(function (array $data, PayrollService $payroll) {
                    $payroll->markPaid($this->record, $data['date'], $data['method'], $data['reference'] ?? null);
                    $this->record->refresh();
                    Notification::make()->title('Payroll marked as paid')->success()->send();
                }),

            ActionGroup::make([
                Action::make('payslips')
                    ->label('All payslips (PDF)')
                    ->icon('heroicon-o-document-duplicate')
                    ->url(fn () => route('payslips.all', $this->record), shouldOpenInNewTab: true),
                Action::make('bank')
                    ->label('Bank / mobile money payment list')
                    ->icon('heroicon-o-building-library')
                    ->url(fn () => route('filament.app.payroll.schedule', [$this->record, 'bank']), shouldOpenInNewTab: true),
                Action::make('paye')
                    ->label('PAYE schedule (URA)')
                    ->icon('heroicon-o-receipt-percent')
                    ->url(fn () => route('filament.app.payroll.schedule', [$this->record, 'paye']), shouldOpenInNewTab: true),
                Action::make('nssfPdf')
                    ->label('NSSF schedule (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => route('nssf-schedule.pdf', $this->record), shouldOpenInNewTab: true),
                Action::make('nssfExcel')
                    ->label('NSSF schedule (Excel)')
                    ->icon('heroicon-o-table-cells')
                    ->url(fn () => route('nssf-schedule.excel', $this->record), shouldOpenInNewTab: true),
                Action::make('lst')
                    ->label('Local Service Tax schedule')
                    ->icon('heroicon-o-map-pin')
                    ->visible(fn () => (float) $this->record->total_lst > 0)
                    ->url(fn () => route('filament.app.payroll.schedule', [$this->record, 'lst']), shouldOpenInNewTab: true),
            ])
                ->label('Documents')
                ->icon('heroicon-o-printer')
                ->button()
                ->color('gray')
                ->visible(fn () => $this->record->staff_count > 0),

            DeleteAction::make()
                ->visible(fn () => $this->record->isDraft())
                ->modalDescription('Deletes this draft payroll and its payslips. Nothing else is affected.'),
        ];
    }
}
