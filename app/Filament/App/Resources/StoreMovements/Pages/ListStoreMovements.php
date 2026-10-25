<?php

namespace App\Filament\App\Resources\StoreMovements\Pages;

use App\Filament\App\Resources\StoreMovements\StoreMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListStoreMovements extends ListRecords
{
    protected static string $resource = StoreMovementResource::class;

    public function getSubheading(): ?string
    {
        return 'Everything received, issued and counted, newest first. Choose an item under Filters to see its stock card.';
    }
}
