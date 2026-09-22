<?php

namespace App\Filament\App\Resources\FinanceCategories\Pages;

use App\Filament\App\Resources\FinanceCategories\FinanceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFinanceCategories extends ManageRecords
{
    protected static string $resource = FinanceCategoryResource::class;

    public function getSubheading(): ?string
    {
        return 'The heads money comes in and goes out under. Switch off any you do not use.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New category')
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id]),
        ];
    }
}
