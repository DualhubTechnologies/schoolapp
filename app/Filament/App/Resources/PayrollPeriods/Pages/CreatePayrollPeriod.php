<?php

namespace App\Filament\App\Resources\PayrollPeriods\Pages;

use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Services\Payroll\PayrollService;
use Filament\Resources\Pages\CreateRecord;

/**
 * Starting a month's payroll calculates it straight away and opens the
 * run, rather than leaving an empty record to come back to.
 */
class CreatePayrollPeriod extends CreateRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected static bool $canCreateAnother = false;

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

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Payroll calculated — review it, then approve';
    }
}
