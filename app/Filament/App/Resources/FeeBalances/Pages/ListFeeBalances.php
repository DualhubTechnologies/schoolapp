<?php

namespace App\Filament\App\Resources\FeeBalances\Pages;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use Filament\Resources\Pages\ListRecords;

class ListFeeBalances extends ListRecords
{
    protected static string $resource = FeeBalanceResource::class;

    public function getTitle(): string
    {
        return 'Student Accounts';
    }

    public function getSubheading(): ?string
    {
        return "Every student's fee account. Click a student to open their statement; select several to send reminders.";
    }
}
