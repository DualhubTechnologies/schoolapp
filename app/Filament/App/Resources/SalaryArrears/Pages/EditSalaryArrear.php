<?php

namespace App\Filament\App\Resources\SalaryArrears\Pages;

use App\Filament\App\Resources\SalaryArrears\SalaryArrearResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditSalaryArrear extends EditRecordPage
{
    protected static string $resource = SalaryArrearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
