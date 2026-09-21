<?php

namespace App\Filament\App\Resources\DeductionTypes\Pages;

use App\Filament\App\Resources\DeductionTypes\DeductionTypeResource;
use App\Support\PayrollDefaults;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDeductionTypes extends ListRecords
{
    protected static string $resource = DeductionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStandard')
                ->label('Add standard types')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Adds the deduction types most Ugandan schools use. Types you already have are left alone.')
                ->action(function () {
                    $added = PayrollDefaults::deductions(auth()->user()->school_id);
                    Notification::make()
                        ->title($added ? "{$added} deduction types added" : 'You already have all the standard types')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
