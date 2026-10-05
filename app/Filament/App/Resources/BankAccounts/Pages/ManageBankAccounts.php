<?php

namespace App\Filament\App\Resources\BankAccounts\Pages;

use App\Filament\App\Resources\BankAccounts\BankAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBankAccounts extends ManageRecords
{
    protected static string $resource = BankAccountResource::class;

    public function getSubheading(): ?string
    {
        return 'Add each account the school banks with, then use Reconcile to import its statement and match it to SchoolHub.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add bank account')
                ->mutateDataUsing(fn (array $data): array => $data + ['school_id' => auth()->user()?->school_id]),
        ];
    }
}
