<?php

namespace App\Filament\App\Resources\ResidencyTypes\Pages;

use App\Filament\App\Resources\ResidencyTypes\ResidencyTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResidencyTypes extends ListRecords
{
    protected static string $resource = ResidencyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
