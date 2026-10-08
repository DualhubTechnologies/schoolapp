<?php

namespace App\Filament\App\Resources\Staff\Pages;

use App\Filament\App\Resources\Staff\StaffResource;
use App\Filament\Support\Pages\CreateRecordPage;
use App\Models\StaffSalary;

class CreateStaff extends CreateRecordPage
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The school picker is only shown to a Super Admin; everyone else
        // adds staff to their own school.
        $data['school_id'] ??= auth()->user()->school_id;

        return $data;
    }

    /** The salary typed on the form becomes their first salary, from the day they were employed. */
    protected function afterCreate(): void
    {
        $salary = $this->data['monthly_salary'] ?? null;

        if (is_numeric($salary) && (float) $salary > 0) {
            StaffSalary::create([
                'school_id' => $this->record->getAttribute('school_id'),
                'staff_id' => $this->record->getKey(),
                'base_salary' => (float) $salary,
                'effective_from' => $this->record->getAttribute('employment_date') ?? today(),
            ]);
        }
    }

    /** Straight to the new person's record, where allowances, deductions and bank details are added. */
    protected function getRedirectUrl(): string
    {
        return StaffResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Staff member saved. Add allowances, deductions or bank details below if they have any.';
    }
}
