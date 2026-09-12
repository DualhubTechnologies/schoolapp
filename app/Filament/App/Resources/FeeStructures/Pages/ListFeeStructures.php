<?php

namespace App\Filament\App\Resources\FeeStructures\Pages;

use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeeStructures extends ListRecords
{
    protected static string $resource = FeeStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
