<?php

namespace App\Filament\App\Resources\ClassLevels\Pages;

use App\Filament\App\Resources\ClassLevels\ClassLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClassLevels extends ListRecords
{
    protected static string $resource = ClassLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
