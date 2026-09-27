<?php

namespace App\Filament\App\Resources\Staff\Pages;

use App\Filament\App\Resources\Staff\StaffResource;
use App\Filament\Support\Pages\CreateRecordPage;

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

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Staff member saved — open them from the list to set salary, allowances and bank details';
    }
}
