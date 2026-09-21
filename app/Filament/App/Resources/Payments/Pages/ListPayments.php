<?php

namespace App\Filament\App\Resources\Payments\Pages;

use App\Filament\App\Resources\Payments\PaymentResource;
use App\Filament\Pages\ReceivePayment;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\App\Resources\Payments\Widgets\CollectionsSummary::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive')
                ->label('Receive payment')
                ->icon('heroicon-o-plus')
                ->url(ReceivePayment::getUrl()),
        ];
    }
}
