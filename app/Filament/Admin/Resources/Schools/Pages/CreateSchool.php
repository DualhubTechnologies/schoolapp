<?php

namespace App\Filament\Admin\Resources\Schools\Pages;

use App\Filament\Admin\Resources\Schools\SchoolResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSchool extends CreateRecord
{
    protected static string $resource = SchoolResource::class;

    /**
     * Resource pages cap their own width regardless of the panel's
     * maxContentWidth, which is what leaves dead space beside the form.
     */
    /** Every new school starts on the free trial. */
    protected function afterCreate(): void
    {
        \App\Services\Subscriptions\SubscriptionManager::startTrial($this->record);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}