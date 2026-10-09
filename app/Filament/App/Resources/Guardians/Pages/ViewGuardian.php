<?php

namespace App\Filament\App\Resources\Guardians\Pages;

use App\Filament\App\Resources\Guardians\GuardianResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A parent's contact details, read only: what teachers (and anyone
 * without Admissions) see.
 */
class ViewGuardian extends ViewRecord
{
    protected static string $resource = GuardianResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return GuardianResource::canEdit($this->record) ? null : 'Only the admissions office can change these details.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => GuardianResource::canEdit($this->record)),
        ];
    }
}
