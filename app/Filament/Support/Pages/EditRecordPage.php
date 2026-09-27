<?php

namespace App\Filament\Support\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

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

    /**
     * Every edit page also gets "History": who created and changed this
     * record, and what they changed (from the activity log), for records
     * that are logged.
     */
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        if (! in_array(LogsActivity::class, class_uses_recursive($this->getRecord()), true)) {
            return;
        }

        $history = Action::make('history')
            ->label('History')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->modalHeading('Who changed this?')
            ->modalDescription('Every change to this record, newest first.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (): View => view('filament.partials.record-history', [
                'activities' => Activity::forSubject($this->getRecord())->with('causer')->latest('id')->limit(50)->get(),
            ]));

        $this->cacheAction($history);
        array_unshift($this->cachedHeaderActions, $history);
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
