<?php

namespace App\Filament\App\Resources\SalaryArrears\Pages;

use App\Filament\App\Resources\SalaryArrears\SalaryArrearResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalaryArrears extends ListRecords
{
    protected static string $resource = SalaryArrearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
