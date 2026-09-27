<?php

namespace App\Filament\App\Resources\Houses\Pages;

use App\Filament\App\Resources\Houses\HouseResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditHouse extends EditRecordPage
{
    protected static string $resource = HouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
