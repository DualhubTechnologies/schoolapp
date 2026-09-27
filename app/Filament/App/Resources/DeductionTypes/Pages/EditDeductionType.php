<?php

namespace App\Filament\App\Resources\DeductionTypes\Pages;

use App\Filament\App\Resources\DeductionTypes\DeductionTypeResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditDeductionType extends EditRecordPage
{
    protected static string $resource = DeductionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
