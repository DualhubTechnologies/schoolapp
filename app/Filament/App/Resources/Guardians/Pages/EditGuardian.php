<?php

namespace App\Filament\App\Resources\Guardians\Pages;

use App\Filament\App\Resources\Guardians\GuardianResource;
use App\Filament\Support\ConfirmWithPassword;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditGuardian extends EditRecordPage
{
    protected static string $resource = GuardianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConfirmWithPassword::on(DeleteAction::make()),
        ];
    }
}
