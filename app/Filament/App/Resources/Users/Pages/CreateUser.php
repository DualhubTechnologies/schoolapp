<?php

namespace App\Filament\App\Resources\Users\Pages;

use App\Filament\App\Resources\Users\Pages\Concerns\SyncsUserAccess;
use App\Filament\App\Resources\Users\UserResource;
use App\Services\Subscriptions\SubscriptionManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SyncsUserAccess;

    protected static string $resource = UserResource::class;

    /** A school at its plan's login limit is told before filling the form. */
    public function mount(): void
    {
        $schoolId = auth()->user()->school_id;

        if ($schoolId && SubscriptionManager::roomForUsers($schoolId) === 0) {
            Notification::make()
                ->title('Plan limit reached')
                ->body('The school has as many staff logins as its plan allows. Delete logins that are no longer used, or ask for a bigger plan on the Subscription page.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(UserResource::getUrl('index'));

            return;
        }

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareAccess($data);
    }

    protected function afterCreate(): void
    {
        $this->syncStaffAndTeaching($this->record);
    }
}
