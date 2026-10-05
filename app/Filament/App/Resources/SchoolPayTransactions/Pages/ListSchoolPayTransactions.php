<?php

namespace App\Filament\App\Resources\SchoolPayTransactions\Pages;

use App\Filament\App\Resources\SchoolPayTransactions\SchoolPayTransactionResource;
use App\Filament\Pages\SchoolProfile;
use App\Services\SchoolPay\SchoolPayPayments;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;

class ListSchoolPayTransactions extends ListRecords
{
    protected static string $resource = SchoolPayTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Check SchoolPay now')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => (bool) auth()->user()?->school?->usesSchoolPay())
                ->action(function (SchoolPayPayments $payments): void {
                    $school = auth()->user()?->school;

                    if (! $school) {
                        return;
                    }

                    try {
                        $counts = $payments->sync($school, 7);
                    } catch (RuntimeException $e) {
                        Notification::make()->title('Could not check SchoolPay')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $new = $counts['recorded'] + $counts['unmatched'] + $counts['other_fees'];

                    Notification::make()
                        ->title($new > 0 ? "{$new} new SchoolPay ".str('payment')->plural($new) : 'Nothing new from SchoolPay')
                        ->body("Last 7 days: {$counts['recorded']} recorded, {$counts['unmatched']} need a learner, {$counts['other_fees']} other fees.")
                        ->success()
                        ->send();
                }),

            Action::make('settings')
                ->label('SchoolPay settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn (): string => SchoolProfile::getUrl())
                ->visible(fn (): bool => SchoolProfile::canAccess()),
        ];
    }
}
