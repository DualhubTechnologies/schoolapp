<?php

namespace App\Filament\App\Resources\DeductionTypes\Pages;

use App\Filament\App\Resources\DeductionTypes\DeductionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeductionTypes extends ListRecords
{
    protected static string $resource = DeductionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
