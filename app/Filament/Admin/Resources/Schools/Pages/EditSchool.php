<?php

namespace App\Filament\Admin\Resources\Schools\Pages;

use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Filament\Admin\Resources\Schools\SubscriptionActions;
use App\Filament\Support\ConfirmWithPassword;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\Width;

class EditSchool extends EditRecordPage
{
    protected static string $resource = SchoolResource::class;

    /**
     * Resource pages cap their own width regardless of the panel's
     * maxContentWidth, which is what leaves dead space beside the form.
     */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * The everyday actions as buttons (Approve and Reject only while the
     * school waits); the rest in a "More" menu, so the school's name is
     * not squeezed into a narrow column beside a row of buttons.
     */
    protected function getHeaderActions(): array
    {
        return [
            SubscriptionActions::approve(),
            SubscriptionActions::reject(),
            SubscriptionActions::renew(),
            ActionGroup::make([
                SubscriptionActions::activationCode(),
                SubscriptionActions::changePlan(),
                SubscriptionActions::extend(),
                SubscriptionActions::suspend(),
                ConfirmWithPassword::on(DeleteAction::make()),
            ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }
}
