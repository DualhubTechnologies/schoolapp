<?php

namespace App\Filament\Resources\AllowanceTypes\Pages;

use App\Filament\Resources\AllowanceTypes\AllowanceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllowanceTypes extends ListRecords
{
    protected static string $resource = AllowanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
