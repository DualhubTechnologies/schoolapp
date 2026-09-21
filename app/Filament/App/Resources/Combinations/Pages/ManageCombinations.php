<?php

namespace App\Filament\App\Resources\Combinations\Pages;

use App\Filament\App\Resources\Combinations\CombinationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCombinations extends ManageRecords
{
    protected static string $resource = CombinationResource::class;

    public function getSubheading(): ?string
    {
        return 'Set each S5/S6 student\'s combination on their student record: it decides which three principal subjects count towards their points.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New combination')
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id]),
        ];
    }
}
