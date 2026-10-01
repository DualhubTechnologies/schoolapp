<?php

namespace App\Filament\Pages;

use App\Models\LicenceKeyRecord;
use App\Models\School;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Edition;
use App\Support\Licensing\DesktopLicence;
use App\Support\Modules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The Windows app's licence: where the school stands, and the box for the
 * licence code SchoolHub sends (FGDH-FWFH-2342-WETR), for a free trial or
 * after paying. A locked school is brought here; only those who manage the
 * school may enter a code. Entering one needs the internet once
 * (DesktopLicence::enter); after that the app works offline.
 */
class Licence extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 91;

    protected static ?string $title = 'Licence';

    protected string $view = 'filament.pages.licence';

    public string $key = '';

    public static function canAccess(): bool
    {
        return Edition::isDesktop() && auth()->user()?->school_id !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && Modules::hasFullAccess(auth()->user());
    }

    public function school(): School
    {
        return School::findOrFail(auth()->user()?->school_id);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return SubscriptionManager::status($this->school());
    }

    public function canEnterKey(): bool
    {
        return Modules::hasFullAccess(auth()->user());
    }

    /** @return Collection<int, LicenceKeyRecord> */
    public function entered(): Collection
    {
        return LicenceKeyRecord::query()->orderByDesc('ends_on')->get();
    }

    /** A WhatsApp message to SchoolHub with the details a licence needs. */
    public function whatsappUrl(): string
    {
        $school = $this->school();
        $text = "Hello SchoolHub, I would like a licence code for the Windows app.\nSchool: {$school->name}";

        return 'https://wa.me/'.config('contact.whatsapp').'?text='.rawurlencode($text);
    }

    public function activate(): void
    {
        abort_unless($this->canEnterKey(), 403);

        if (trim($this->key) === '') {
            $this->addError('key', 'Type the licence code SchoolHub sent you.');

            return;
        }

        try {
            $licence = DesktopLicence::enter($this->school(), $this->key);
        } catch (RuntimeException $e) {
            $this->addError('key', $e->getMessage());

            return;
        }

        $this->key = '';
        $this->resetErrorBag();

        Notification::make()
            ->title('Licence entered')
            ->body("{$licence->details['plan']} plan until {$licence->endsOn()->format('j M Y')}. Thank you.")
            ->success()
            ->send();
    }
}
