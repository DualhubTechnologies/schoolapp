<?php

namespace App\Filament\App\Resources\ClassLevels\Pages;

use App\Filament\App\Resources\ClassLevels\ClassLevelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClassLevel extends EditRecord
{
    protected static string $resource = ClassLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
