<?php

namespace App\Filament\Admin\Resources\Schools\Pages;

use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Filament\Admin\Resources\Schools\SubscriptionActions;
use App\Filament\Support\Pages\EditRecordPage;
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

    protected function getHeaderActions(): array
    {
        return [
            ...SubscriptionActions::all(),
            DeleteAction::make(),
        ];
    }
}
