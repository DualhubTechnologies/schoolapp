<?php

namespace App\Filament\App\Resources\Sections\Pages;

use App\Filament\App\Resources\Sections\SectionResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditSection extends EditRecordPage
{
    protected static string $resource = SectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
