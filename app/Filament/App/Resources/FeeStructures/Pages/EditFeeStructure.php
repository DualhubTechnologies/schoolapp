<?php

namespace App\Filament\App\Resources\FeeStructures\Pages;

use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Filament\Support\Pages\EditRecordPage;
use App\Models\FeeStructure;
use Filament\Actions\DeleteAction;

class EditFeeStructure extends EditRecordPage
{
    protected static string $resource = FeeStructureResource::class;

    /**
     * As on create: only a termly fee belongs to a term, and who pays
     * follows from how often.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['frequency'] ?? 'per_term') !== 'per_term') {
            $data['term_id'] = null;
        }

        return FeeStructure::withWhoPays($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
