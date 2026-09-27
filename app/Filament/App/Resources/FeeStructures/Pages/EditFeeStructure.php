<?php

namespace App\Filament\App\Resources\FeeStructures\Pages;

use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditFeeStructure extends EditRecordPage
{
    protected static string $resource = FeeStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
