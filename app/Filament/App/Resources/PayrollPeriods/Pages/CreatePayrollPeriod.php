<?php

namespace App\Filament\App\Resources\PayrollPeriods\Pages;

use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Support\Pages\CreateRecordPage;
use App\Services\Payroll\PayrollService;

/**
 * Starting a month's payroll calculates it straight away, then opens it
 * to be reviewed and approved.
 */
class CreatePayrollPeriod extends CreateRecordPage
{
    protected static string $resource = PayrollPeriodResource::class;

    public function getTitle(): string
    {
        return "Start this month's payroll";
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

    /** Straight into the new payroll, to check it and approve it. */
    protected function getRedirectUrl(): string
    {
        return PayrollPeriodResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Payroll calculated. Check it below, then approve it.';
    }
}
