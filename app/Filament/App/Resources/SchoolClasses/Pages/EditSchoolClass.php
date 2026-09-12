<?php

namespace App\Filament\App\Resources\SchoolClasses\Pages;

use App\Filament\App\Resources\SchoolClasses\SchoolClassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchoolClass extends EditRecord
{
    protected static string $resource = SchoolClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
