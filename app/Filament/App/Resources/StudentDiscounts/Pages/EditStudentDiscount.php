<?php

namespace App\Filament\App\Resources\StudentDiscounts\Pages;

use App\Filament\App\Resources\StudentDiscounts\StudentDiscountResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditStudentDiscount extends EditRecordPage
{
    protected static string $resource = StudentDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
