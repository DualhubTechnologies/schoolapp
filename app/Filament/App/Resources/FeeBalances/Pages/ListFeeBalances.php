<?php

namespace App\Filament\App\Resources\FeeBalances\Pages;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use Filament\Resources\Pages\ListRecords;

class ListFeeBalances extends ListRecords
{
    protected static string $resource = FeeBalanceResource::class;

    public function getTitle(): string
    {
        return 'Fee Balances';
    }

    public function getSubheading(): ?string
    {
        return 'Who has paid, who is still owing. Percentages are of the total owed, arrears included.';
    }
}
