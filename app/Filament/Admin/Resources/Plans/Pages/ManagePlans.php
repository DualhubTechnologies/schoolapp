<?php

namespace App\Filament\Admin\Resources\Plans\Pages;

use App\Filament\Admin\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePlans extends ManageRecords
{
    protected static string $resource = PlanResource::class;

    protected ?string $subheading = 'Every plan includes every module. Plans differ only by the number of active students and staff logins.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New plan'),
        ];
    }
}
