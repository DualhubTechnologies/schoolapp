<?php

namespace App\Filament\App\Resources\Staff\Pages;

use App\Filament\App\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The school picker is only shown to a Super Admin; everyone else
        // adds staff to their own school.
        $data['school_id'] ??= auth()->user()->school_id;

        return $data;
    }

    /**
     * Straight to the new record: salary, allowances, deductions and bank
     * details are set in the tabs there.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Staff member saved — now set their salary and allowances below';
    }
}
