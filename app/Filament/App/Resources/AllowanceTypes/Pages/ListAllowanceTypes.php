<?php

namespace App\Filament\App\Resources\AllowanceTypes\Pages;

use App\Filament\App\Resources\AllowanceTypes\AllowanceTypeResource;
use App\Support\PayrollDefaults;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAllowanceTypes extends ListRecords
{
    protected static string $resource = AllowanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStandard')
                ->label('Add standard types')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Adds the allowance types most Ugandan schools use. Types you already have are left alone.')
                ->action(function () {
                    $added = PayrollDefaults::allowances(auth()->user()->school_id);
                    Notification::make()
                        ->title($added ? "{$added} allowance types added" : 'You already have all the standard types')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
