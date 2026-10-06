<?php

namespace App\Filament\Pages;

use App\Filament\Admin\Resources\Schools\Schemas\SchoolForm;
use App\Filament\App\Resources\SchoolPayTransactions\SchoolPayTransactionResource;
use App\Support\Edition;
use App\Support\Modules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Settings -> SchoolPay: the school turns on SchoolPay, enters its school
 * code and API password, and copies the web hook address into its
 * SchoolPay portal. Not in the offline (desktop) edition, which SchoolPay
 * cannot reach.
 *
 * @property-read Schema $form
 */
class SchoolPaySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'SchoolPay';

    protected static ?string $title = 'SchoolPay';

    protected static ?string $slug = 'settings/schoolpay';

    protected string $view = 'filament.pages.school-pay-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ! Edition::isDesktop() && filled(auth()->user()?->school_id) && Modules::allows('settings');
    }

    public function mount(): void
    {
        $school = auth()->user()?->school;

        abort_if(! $school, 404, 'School not found.');

        $this->form->fill($school->only(['schoolpay_enabled', 'schoolpay_school_code']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(auth()->user()?->school)
            ->statePath('data')
            ->components([
                SchoolForm::schoolPaySection()->collapsible(false),
            ]);
    }

    public function save(): void
    {
        $school = auth()->user()?->school;

        abort_if(! $school, 404, 'School not found.');

        $school->update($this->form->getState());

        Notification::make()
            ->title('SchoolPay settings saved')
            ->body($school->usesSchoolPay()
                ? 'Copy the web hook address into your SchoolPay school portal to receive payments as they are made.'
                : 'SchoolPay is off: payments will not be recorded automatically.')
            ->success()
            ->send();

        $this->form->fill($school->fresh()?->only(['schoolpay_enabled', 'schoolpay_school_code']) ?? []);
    }

    public function paymentsUrl(): string
    {
        return SchoolPayTransactionResource::getUrl();
    }
}
