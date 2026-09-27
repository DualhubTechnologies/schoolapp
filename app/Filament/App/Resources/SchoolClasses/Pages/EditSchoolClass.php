<?php

namespace App\Filament\App\Resources\SchoolClasses\Pages;

use App\Filament\App\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditSchoolClass extends EditRecordPage
{
    protected static string $resource = SchoolClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
