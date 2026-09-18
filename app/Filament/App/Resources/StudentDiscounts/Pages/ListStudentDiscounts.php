<?php

namespace App\Filament\App\Resources\StudentDiscounts\Pages;

use App\Filament\App\Resources\StudentDiscounts\StudentDiscountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentDiscounts extends ListRecords
{
    protected static string $resource = StudentDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New discount'),
        ];
    }
}
