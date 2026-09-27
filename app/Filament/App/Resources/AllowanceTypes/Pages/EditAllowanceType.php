<?php

namespace App\Filament\App\Resources\AllowanceTypes\Pages;

use App\Filament\App\Resources\AllowanceTypes\AllowanceTypeResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditAllowanceType extends EditRecordPage
{
    protected static string $resource = AllowanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
