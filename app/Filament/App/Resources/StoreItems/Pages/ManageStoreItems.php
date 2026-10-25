<?php

namespace App\Filament\App\Resources\StoreItems\Pages;

use App\Filament\App\Resources\StoreItems\StoreItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStoreItems extends ManageRecords
{
    protected static string $resource = StoreItemResource::class;

    protected static ?string $title = 'Store Items';

    public function getSubheading(): ?string
    {
        return 'What the stores keep and how much is left. Receive stock as it is delivered, issue it as it is used, and count it now and then.';
    }

    protected function getHeaderActions(): array
    {
        return [
            StoreItemResource::receiveAction(chooseItem: true),
            StoreItemResource::issueAction(chooseItem: true),
            CreateAction::make()
                ->label('New item')
                ->color('gray')
                ->mutateDataUsing(fn (array $data): array => $data + ['school_id' => auth()->user()?->school_id, 'quantity' => 0]),
        ];
    }
}
