<?php

namespace App\Filament\App\Resources\ResidencyTypes\Pages;

use App\Filament\App\Resources\ResidencyTypes\ResidencyTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResidencyType extends EditRecord
{
    protected static string $resource = ResidencyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
