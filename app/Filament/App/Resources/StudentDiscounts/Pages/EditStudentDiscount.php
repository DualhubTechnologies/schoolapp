<?php

namespace App\Filament\App\Resources\StudentDiscounts\Pages;

use App\Filament\App\Resources\StudentDiscounts\StudentDiscountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentDiscount extends EditRecord
{
    protected static string $resource = StudentDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
