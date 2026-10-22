<?php

namespace App\Filament\Pages;

use App\Exceptions\InvalidActivationCode;
use App\Filament\Concerns\PlacedInMenu;
use App\Models\MomoPayment;
use App\Models\Plan;
use App\Models\School;
use App\Models\SubscriptionPayment;
use App\Services\Payments\MtnMomo;
use App\Services\Subscriptions\MomoCheckout;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Edition;
use App\Support\Modules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use RuntimeException;

/**
 * The school's own view of its SchoolHub subscription: plan, days left,
 * usage against the plan's limits, how to pay, and payment history.
 *
 * @property-read School $school
 * @property-read Collection<int, Plan> $plans
 * @property-read MomoPayment|null $momoPayment
 */
class SchoolSubscription extends Page
{
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

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

        // The Windows app pays by licence key instead (Licence page).
        if (! $user?->school_id || $user->hasRole('Super Admin') || Edition::isDesktop()) {
            return false;
        }

        return Modules::hasFullAccess($user) || SubscriptionManager::isLocked($user->school_id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Modules::hasFullAccess(auth()->user()) && static::canAccess();
    }

    /** Plans and payment are held back until SchoolHub has approved the school. */
    public function isManager(): bool
    {
        return Modules::hasFullAccess(auth()->user())
            && ! in_array($this->school->status, ['pending', 'rejected'], true);
    }

    public ?int $codeModalPlanId = null;

    /**
     * New students the school is trying to add (from a student import that
     * would not fit): the plans are then suggested for that many more.
     */
    #[Url(as: 'students')]
    public ?int $adding = null;

    /** Active students the school needs room for: today's, plus any it is adding. */
    public function studentsNeeded(): int
    {
        return $this->usage['students']['used'] + max(0, (int) $this->adding);
    }

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

    // ── Pay now: an MTN MoMo prompt on the school's phone ──

    public ?int $payPlanId = null;

    public string $payCycle = 'term';

    public string $payPhone = '';

    public ?int $momoPaymentId = null;

    /** MTN MoMo is set up, and this person may pay. */
    public function canPayByPhone(): bool
    {
        return $this->isManager() && MtnMomo::isConfigured();
    }

    public function openPay(int $planId): void
    {
        if (! $this->canPayByPhone()) {
            return;
        }

        $this->payPlanId = $planId;
        $this->payCycle = 'term';
        $this->payPhone = (string) (auth()->user()->phone ?? '');
        $this->momoPaymentId = null;
    }

    public function closePay(): void
    {
        $this->payPlanId = null;
        $this->momoPaymentId = null;
        unset($this->momoPayment);
    }

    public function payNow(MomoCheckout $checkout): void
    {
        $plan = $this->plans->firstWhere('id', $this->payPlanId);

        if (! $this->canPayByPhone() || ! $plan) {
            return;
        }

        try {
            $payment = $checkout->start($this->school, auth()->user(), $plan, $this->payCycle, $this->payPhone);
        } catch (RuntimeException $e) {
            Notification::make()->title('Payment not started')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->momoPaymentId = $payment->getKey();
        unset($this->momoPayment);
    }

    /** Polled while the prompt is on the phone. */
    public function checkMomo(MomoCheckout $checkout): void
    {
        $payment = $this->momoPayment;

        if (! $payment?->isPending()) {
            return;
        }

        $payment = $checkout->refresh($payment);
        unset($this->momoPayment);

        if ($payment->status === MomoPayment::SUCCESSFUL) {
            unset($this->status, $this->usage, $this->payments);
        }
    }

    #[Computed]
    public function momoPayment(): ?MomoPayment
    {
        return $this->momoPaymentId
            ? MomoPayment::where('school_id', $this->school->getKey())->with('subscription')->find($this->momoPaymentId)
            : null;
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

    /** Smallest plan that fits the school: today's students plus any it is adding. */
    public function suggestedPlanId(): ?int
    {
        $students = $this->studentsNeeded();
        $users = $this->usage['users']['used'];

        return $this->plans
            ->first(fn (Plan $p) => ($p->max_students === null || $p->max_students >= $students) && ($p->max_users === null || $p->max_users >= $users))
            ?->getKey();
    }
}
