<?php

namespace App\Filament\Pages;

use App\Filament\App\Pages\Dashboard;
use App\Models\School;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Where everyone at a school that registered itself waits until the
 * platform owner approves it (or sees why it was turned down): what
 * happens next, the details they gave, and how to reach SchoolHub.
 * EnsureSchoolSubscribed sends them here from every other page.
 */
class AwaitingApproval extends Page
{
    protected static ?string $slug = 'awaiting-approval';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.awaiting-approval';

    /** School statuses that are held on this page. */
    public const STATUSES = ['pending', 'rejected'];

    /** Anyone at a school; those not waiting are sent on (mount). */
    public static function canAccess(): bool
    {
        return auth()->user()?->school_id !== null;
    }

    public static function isWaiting(?User $user): bool
    {
        return in_array($user?->school?->status, self::STATUSES, true)
            && ! $user->hasRole('Super Admin');
    }

    public function mount(): void
    {
        // Approved since the page was bookmarked: straight in.
        if (! static::isWaiting(auth()->user())) {
            $this->redirect(Dashboard::getUrl());
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->isRejected() ? 'Registration not approved' : 'Awaiting approval';
    }

    public function school(): School
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->school;
    }

    public function isRejected(): bool
    {
        return $this->school()->status === 'rejected';
    }

    /**
     * WhatsApp link to the SchoolHub team, saying which school is asking.
     */
    public function whatsappUrl(): string
    {
        $school = $this->school();
        $text = $this->isRejected()
            ? "Hello SchoolHub, I'm asking about the registration of {$school->name} ({$school->unique_code}), which was not approved."
            : "Hello SchoolHub, I registered {$school->name} ({$school->unique_code}) and I'm waiting for approval.";

        return 'https://wa.me/'.config('contact.whatsapp').'?text='.rawurlencode($text);
    }
}
