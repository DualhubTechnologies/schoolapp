<?php

namespace App\Filament\App\Resources\Incomes\Pages;

use App\Filament\App\Resources\Incomes\IncomeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIncomes extends ManageRecords
{
    protected static string $resource = IncomeResource::class;

    public function getSubheading(): ?string
    {
        return 'Money received besides school fees — capitation grants, donations, sales, hire of facilities. Fees are counted automatically from receipts.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record income')
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id, 'type' => 'income']),
        ];
    }
}
