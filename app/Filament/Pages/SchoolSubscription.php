<?php

namespace App\Filament\Pages;

use App\Exceptions\InvalidActivationCode;
use App\Models\Plan;
use App\Models\School;
use App\Models\SubscriptionPayment;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Modules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * The school's own view of its SchoolHub subscription: plan, days left,
 * usage against the plan's limits, how to pay, and payment history.
 */
class SchoolSubscription extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Subscription';

    protected static ?string $title = 'Subscription';

    protected static ?string $slug = 'subscription';

    protected string $view = 'filament.pages.school-subscription';

    public string $activationCode = '';

    /**
     * School Admins manage it. Anyone at a locked school is sent here so
     * they understand why (without the payment details).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user?->school_id || $user->hasRole('Super Admin')) {
            return false;
        }

        return Modules::hasFullAccess($user) || SubscriptionManager::isLocked($user->school_id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Modules::hasFullAccess(auth()->user()) && static::canAccess();
    }

    public function isManager(): bool
    {
        return Modules::hasFullAccess(auth()->user());
    }

    public ?int $codeModalPlanId = null;

    /** They clicked "Activate with a code" on a plan card -- open the prompt for that plan. */
    public function chooseplan(int $planId): void
    {
        if (! $this->isManager()) {
            return;
        }

        $this->codeModalPlanId = $planId;
        $this->activationCode = '';
    }

    public function closeCodeModal(): void
    {
        $this->codeModalPlanId = null;
        $this->activationCode = '';
    }

    /**
     * Paid, got a code from SchoolHub, typed it in -- applies that exact
     * renewal immediately. No admin visit needed.
     */
    public function redeemCode(): void
    {
        if (! $this->isManager() || blank($this->activationCode)) {
            return;
        }

        try {
            SubscriptionManager::redeemActivationCode($this->school, $this->activationCode, $this->codeModalPlanId);
        } catch (InvalidActivationCode $e) {
            Notification::make()->title('Invalid code')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->activationCode = '';
        $this->codeModalPlanId = null;
        unset($this->status, $this->usage, $this->payments);

        Notification::make()->title('Activated')->body('Your subscription has been updated.')->success()->send();
    }

    #[Computed]
    public function school(): School
    {
        return School::findOrFail(auth()->user()->school_id);
    }

    #[Computed]
    public function status(): array
    {
        return SubscriptionManager::status($this->school);
    }

    #[Computed]
    public function usage(): array
    {
        return SubscriptionManager::usage($this->school);
    }

    /** @return Collection<int, Plan> */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->get();
    }

    /** @return Collection<int, SubscriptionPayment> */
    #[Computed]
    public function payments(): Collection
    {
        return SubscriptionPayment::where('school_id', $this->school->getKey())->with('subscription.plan')->orderByDesc('paid_on')->limit(20)->get();
    }

    /** Smallest plan that fits the school as it is today. */
    public function suggestedPlanId(): ?int
    {
        $students = $this->usage['students']['used'];
        $users = $this->usage['users']['used'];

        return $this->plans
            ->first(fn (Plan $p) => ($p->max_students === null || $p->max_students >= $students) && ($p->max_users === null || $p->max_users >= $users))
            ?->getKey();
    }
}
