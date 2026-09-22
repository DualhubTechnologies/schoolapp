<?php

namespace App\Filament\App\Resources\Students\Pages;

use App\Filament\App\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    /** A school at its plan's student limit is told before filling the form. */
    public function mount(): void
    {
        if (\App\Services\Subscriptions\SubscriptionManager::roomForStudents(auth()->user()->school_id) === 0) {
            \Filament\Notifications\Notification::make()
                ->title('Plan limit reached')
                ->body('The school has as many active students as its plan allows. Mark students who have left as Withdrawn/Transferred/Completed, or ask for a bigger plan on the Subscription page.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(StudentResource::getUrl('index'));

            return;
        }

        parent::mount();
    }
}
