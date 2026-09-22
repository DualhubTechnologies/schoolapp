<?php

namespace App\Filament\App\Resources\Users\Pages;

use App\Filament\App\Resources\Users\Pages\Concerns\SyncsUserAccess;
use App\Filament\App\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SyncsUserAccess;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareAccess($data);
    }

    protected function afterCreate(): void
    {
        $this->syncStaffAndTeaching($this->record);
    }
}
