<?php

namespace App\Filament\Admin\Resources\ActivationCodes\Pages;

use App\Filament\Admin\Resources\ActivationCodes\ActivationCodeResource;
use Filament\Resources\Pages\ManageRecords;

class ManageActivationCodes extends ManageRecords
{
    protected static string $resource = ActivationCodeResource::class;

    protected ?string $subheading = 'Codes tied to a school, and spare stock still waiting to be claimed.';

    protected function getHeaderActions(): array
    {
        return [
            ActivationCodeResource::generateAction(),
        ];
    }
}
