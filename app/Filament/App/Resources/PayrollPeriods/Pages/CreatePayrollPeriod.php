<?php

namespace App\Filament\App\Resources\PayrollPeriods\Pages;

use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Support\Pages\CreateRecordPage;
use App\Services\Payroll\PayrollService;

/**
 * Starting a month's payroll calculates it straight away, then returns to
 * the payroll list, where the new run waits to be reviewed and approved.
 */
class CreatePayrollPeriod extends CreateRecordPage
{
    protected static string $resource = PayrollPeriodResource::class;

    public function getTitle(): string
    {
        return 'Start a payroll';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['school_id'] ??= auth()->user()->school_id;
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        app(PayrollService::class)->generate($this->record);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Payroll calculated — open it from the list to review and approve';
    }
}
