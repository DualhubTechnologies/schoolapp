<?php

namespace App\Filament\App\Resources\Terms\Pages;

use App\Filament\App\Resources\Terms\TermResource;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditTerm extends EditRecordPage
{
    protected static string $resource = TermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
