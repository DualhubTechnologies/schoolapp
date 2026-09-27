<?php

namespace App\Filament\Support\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The house "new record" page. Every resource's Create page extends this
 * instead of Filament's CreateRecord, so they all behave the same:
 *
 *   - one Save button (no "save & add another": saving always returns
 *     you to the list, where the new record is shown);
 *   - Cancel and Save in a bar that stays in view at the bottom right;
 *   - a "back to the list" link above the heading (see AppPanelProvider,
 *     render hook scope RecordFormScope::NAME).
 */
abstract class CreateRecordPage extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    public static bool $formActionsAreSticky = true;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    public function getSubheading(): string|Htmlable|null
    {
        return parent::getSubheading() ?? 'Fill in the details below, then press Save. Fields marked * are required.';
    }

    /**
     * End-aligned actions are laid out right to left, so Save (listed
     * first) sits at the far right with Cancel beside it.
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->icon('heroicon-m-check'),
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
