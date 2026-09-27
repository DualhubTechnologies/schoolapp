<?php

namespace App\Filament\App\Resources\ClassLevels\Pages;

use App\Filament\App\Resources\ClassLevels\ClassLevelResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditClassLevel extends EditRecordPage
{
    protected static string $resource = ClassLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
