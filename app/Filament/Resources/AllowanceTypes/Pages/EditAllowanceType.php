<?php

namespace App\Filament\Resources\AllowanceTypes\Pages;

use App\Filament\Resources\AllowanceTypes\AllowanceTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAllowanceType extends EditRecord
{
    protected static string $resource = AllowanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
