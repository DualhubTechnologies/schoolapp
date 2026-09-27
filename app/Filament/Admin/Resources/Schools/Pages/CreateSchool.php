<?php

namespace App\Filament\Admin\Resources\Schools\Pages;

use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Filament\Support\Pages\CreateRecordPage;
use App\Services\Subscriptions\SubscriptionManager;
use Filament\Support\Enums\Width;

class CreateSchool extends CreateRecordPage
{
    protected static string $resource = SchoolResource::class;

    /**
     * Resource pages cap their own width regardless of the panel's
     * maxContentWidth, which is what leaves dead space beside the form.
     */
    /** Every new school starts on the free trial. */
    protected function afterCreate(): void
    {
        SubscriptionManager::startTrial($this->record);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
