<?php

namespace App\Filament\Support\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The house "edit record" page: Save returns to the list, Cancel and Save
 * stay in view at the bottom right, and a "back to the list" link sits
 * above the heading. Every resource's Edit page extends this.
 */
abstract class EditRecordPage extends EditRecord
{
    public static bool $formActionsAreSticky = true;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    public function getSubheading(): string|Htmlable|null
    {
        return parent::getSubheading() ?? 'Make your changes, then press Save. Fields marked * are required.';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->icon('heroicon-m-check'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    public function getRenderHookScopes(): array
    {
        return [...parent::getRenderHookScopes(), RecordFormScope::NAME];
    }
}
