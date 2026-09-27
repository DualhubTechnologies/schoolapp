<?php

namespace App\Filament\App\Resources\AcademicYears\Pages;

use App\Filament\App\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditAcademicYear extends EditRecordPage
{
    protected static string $resource = AcademicYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
