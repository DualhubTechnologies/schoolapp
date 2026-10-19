<?php

namespace App\Filament\App\Resources\SickBayMedicines\Pages;

use App\Filament\App\Resources\SickBayMedicines\SickBayMedicineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSickBayMedicines extends ManageRecords
{
    protected static string $resource = SickBayMedicineResource::class;

    public function getSubheading(): ?string
    {
        return 'Optional. List the sick bay\'s medicines to have stock counted down as the nurse gives them, with a warning when one runs low.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New medicine')
                ->mutateDataUsing(fn (array $data): array => $data + ['school_id' => auth()->user()?->school_id]),
        ];
    }
}
