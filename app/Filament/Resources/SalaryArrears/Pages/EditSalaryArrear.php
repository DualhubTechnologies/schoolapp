<?php

namespace App\Filament\Resources\SalaryArrears\Pages;

use App\Filament\Resources\SalaryArrears\SalaryArrearResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalaryArrear extends EditRecord
{
    protected static string $resource = SalaryArrearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
