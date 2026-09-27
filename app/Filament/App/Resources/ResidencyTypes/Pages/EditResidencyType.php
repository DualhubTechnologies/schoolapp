<?php

namespace App\Filament\App\Resources\ResidencyTypes\Pages;

use App\Filament\App\Resources\ResidencyTypes\ResidencyTypeResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditResidencyType extends EditRecordPage
{
    protected static string $resource = ResidencyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
