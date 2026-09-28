<?php

namespace App\Services\Subscriptions;

use App\Exceptions\InvalidActivationCode;
use App\Exceptions\PlanLimitReached;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionActivationCode;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where a school stands with SchoolHub: its plan, how long it has left,
 * how much of the plan it uses, and whether it may add more.
 *
 * States:
 *   trial      on the free trial
 *   active     paid up
 *   grace      period ended, still working for a few days (red banner)
 *   expired    grace over -- locked until payment is recorded
 *   none       never subscribed -- locked
 *   suspended  switched off by the platform owner -- locked
 *   pending    registered itself, awaiting the platform owner's approval
 *   rejected   registration turned down
 *
 * Status is worked out from dates every time, so there is no nightly job
 * to forget and nothing to drift.
 */
class SubscriptionManager
{
    public const LOCKED = ['expired', 'none', 'suspended', 'pending', 'rejected'];

    /** The period in force today (or the last one, once it has ended). */
    public static function current(School|int $school): ?Subscription
    {
        return Subscription::with('plan')
            ->where('school_id', static::id($school))
            ->where('is_cancelled', false)
            ->where('starts_on', '<=', today())
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{state: string, subscription: ?Subscription, plan: ?Plan, ends_on: ?CarbonImmutable, days_left: ?int, grace_ends_on: ?CarbonImmutable, expiring: bool}
     */
    public static function status(School|int $school): array
    {
        $school = $school instanceof School ? $school : School::find($school);
        $current = $school ? static::current($school) : null;

        // Paid-ahead periods extend the end date.
        $endsOn = $school
            ? Subscription::where('school_id', $school->getKey())->where('is_cancelled', false)->max('ends_on')
            : null;
        $endsOn = $endsOn ? CarbonImmutable::parse($endsOn)->startOfDay() : null;
        $today = CarbonImmutable::today();
        $graceEnds = $endsOn?->addDays((int) config('subscriptions.grace_days', 14));
        $daysLeft = $endsOn ? (int) $today->diffInDays($endsOn, false) : null;

        $state = match (true) {
            ! $school => 'none',
            $school->status === 'pending' => 'pending',
            $school->status === 'rejected' => 'rejected',
            $school->status === 'suspended' => 'suspended',
            ! $current || ! $endsOn => 'none',
            $today->lte($endsOn) => $current->isTrial() ? 'trial' : 'active',
            $today->lte($graceEnds) => 'grace',
            default => 'expired',
        };

        return [
            'state' => $state,
            'subscription' => $current,
            'plan' => $current?->plan,
            'ends_on' => $endsOn,
            'days_left' => $daysLeft,
            'grace_ends_on' => $graceEnds,
            'expiring' => in_array($state, ['trial', 'active'], true) && $daysLeft !== null && $daysLeft <= (int) config('subscriptions.warn_days', 14),
        ];
    }

    public static function isLocked(School|int $school): bool
    {
        return in_array(static::status($school)['state'], self::LOCKED, true);
    }

    // ── Usage and limits ──

    /** Active students: those who have left or completed do not count. */
    public static function studentCount(School|int $school): int
    {
        return Student::where('school_id', static::id($school))->where('status', 'active')->count();
    }

    /** Staff logins; parents and students do not count. */
    public static function userCount(School|int $school): int
    {
        $free = config('subscriptions.free_roles', []);

        return User::where('school_id', static::id($school))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->where(fn ($q) => $q
                ->whereDoesntHave('roles')
                ->orWhereHas('roles', fn ($r) => $r->whereNotIn('name', $free)))
            ->count();
    }

    /**
     * @return array{students: array{used: int, limit: ?int}, users: array{used: int, limit: ?int}}
     */
    public static function usage(School|int $school): array
    {
        $plan = static::current($school)?->plan;

        return [
            'students' => ['used' => static::studentCount($school), 'limit' => $plan?->max_students],
            'users' => ['used' => static::userCount($school), 'limit' => $plan?->max_users],
        ];
    }

    /** How many more active students the plan allows (null = no limit). */
    public static function roomForStudents(School|int $school): ?int
    {
        $limit = static::current($school)?->plan?->max_students;

        return $limit === null ? null : max(0, $limit - static::studentCount($school));
    }

    public static function roomForUsers(School|int $school): ?int
    {
        $limit = static::current($school)?->plan?->max_users;

        return $limit === null ? null : max(0, $limit - static::userCount($school));
    }

    public static function ensureRoomForStudents(School|int $school, int $adding = 1): void
    {
        $room = static::roomForStudents($school);

        if ($room !== null && $room < $adding) {
            $plan = static::current($school)->plan;

            throw new PlanLimitReached(
                "Your {$plan->name} plan allows ".number_format($plan->max_students).' active students and the school has reached it. '
                .'Mark students who have left as Withdrawn/Transferred/Completed, or ask for a bigger plan on the Subscription page.'
            );
        }
    }

    public static function ensureRoomForUsers(School|int $school, int $adding = 1): void
    {
        $room = static::roomForUsers($school);

        if ($room !== null && $room < $adding) {
            $plan = static::current($school)->plan;

            throw new PlanLimitReached(
                "Your {$plan->name} plan allows ".number_format($plan->max_users).' staff logins and the school has reached it. '
                .'Delete logins that are no longer used, or ask for a bigger plan on the Subscription page.'
            );
        }
    }

    // ── Changes (platform owner) ──

    /**
     * Let a school that registered itself in. Its trial is re-dated to
     * start today, so days spent waiting for approval are not lost.
     */
    public static function approve(School $school): void
    {
        DB::transaction(function () use ($school) {
            $school->update([
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => auth()->user()?->name,
                'rejection_reason' => null,
            ]);

            $trial = $school->subscriptions()->where('cycle', 'trial')->where('is_cancelled', false)->first();

            if (! $trial) {
                static::startTrial($school);

                return;
            }

            $days = (int) $trial->starts_on->diffInDays($trial->ends_on);
            $trial->update(['starts_on' => today(), 'ends_on' => today()->addDays($days)]);
        });
    }

    /** Turn down a school that registered itself; its records are kept. */
    public static function reject(School $school, ?string $reason = null): void
    {
        $school->update(['status' => 'rejected', 'rejection_reason' => $reason]);
    }

    public static function startTrial(School $school, ?Plan $plan = null): Subscription
    {
        $plan ??= Plan::where('is_trial', true)->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $days = (int) config('subscriptions.trial_days', 30);

        return Subscription::create([
            'school_id' => $school->getKey(),
            'plan_id' => $plan->getKey(),
            'cycle' => 'trial',
            'starts_on' => today(),
            'ends_on' => today()->addDays($days - 1),
            'amount' => 0,
            'created_by' => auth()->user()?->name,
        ]);
    }

    /**
     * Record a payment and add the period it pays for. A renewal made
     * early starts the day after the current period ends, so the school
     * never loses days; a trial is replaced from today. Paying for a
     * different plan than the one already in force switches to it
     * immediately -- the school gets the new plan's limits today rather
     * than waiting out whatever is left of the old one.
     *
     * @param  array{amount?: float, method?: string, reference?: ?string, paid_on?: mixed, notes?: ?string}|null  $payment
     */
    public static function renew(School $school, Plan $plan, string $cycle, float $amount, ?array $payment = null, mixed $customEndsOn = null, ?string $notes = null): Subscription
    {
        return DB::transaction(function () use ($school, $plan, $cycle, $amount, $payment, $customEndsOn, $notes) {
            $current = static::current($school);
            $carryDays = 0;
            $switchingPlan = false;

            // Paying for a different plan than the one in force today
            // switches to it immediately. The old period is cut short as
            // of today -- rather than renamed -- so the payment already
            // recorded against it keeps its correct plan in history;
            // whatever was left of it is carried forward as extra days on
            // the new plan instead, so no paid time is lost.
            if ($current && ! $current->isTrial() && $current->plan_id !== $plan->getKey()) {
                $oldEnd = CarbonImmutable::parse($current->ends_on);
                $today = CarbonImmutable::today();

                if ($oldEnd->gt($today)) {
                    $carryDays = $today->diffInDays($oldEnd);
                    $current->update(['ends_on' => $today->subDay()->max(CarbonImmutable::parse($current->starts_on))]);
                }

                $switchingPlan = true;
            }

            $lastEnd = Subscription::where('school_id', $school->getKey())
                ->where('is_cancelled', false)
                ->where('cycle', '!=', 'trial')
                ->max('ends_on');

            $start = CarbonImmutable::today();

            // A switch always starts today, whatever the stacking math
            // above would otherwise say -- that is the whole point of it.
            if (! $switchingPlan && $lastEnd && CarbonImmutable::parse($lastEnd)->gte($start)) {
                $start = CarbonImmutable::parse($lastEnd)->addDay();
            }

            $end = $cycle === 'custom' && $customEndsOn
                ? CarbonImmutable::parse($customEndsOn)
                : $start->addMonths((int) config("subscriptions.cycle_months.{$cycle}", 4))->subDay()->addDays($carryDays);

            // A trial stops the day before the paid period starts.
            if ($current?->isTrial() && $current->ends_on->gte($start)) {
                $current->update(['ends_on' => $start->subDay()->max(CarbonImmutable::parse($current->starts_on))]);

                if ($start->lte($current->starts_on)) {
                    $current->update(['is_cancelled' => true]);
                }
            }

            $subscription = Subscription::create([
                'school_id' => $school->getKey(),
                'plan_id' => $plan->getKey(),
                'cycle' => $cycle,
                'starts_on' => $start,
                'ends_on' => $end,
                'amount' => $amount,
                'notes' => $notes,
                'created_by' => auth()->user()?->name,
            ]);

            if ($payment && ($payment['amount'] ?? 0) > 0) {
                SubscriptionPayment::create([
                    'school_id' => $school->getKey(),
                    'subscription_id' => $subscription->getKey(),
                    'amount' => $payment['amount'],
                    'method' => $payment['method'] ?? 'mobile_money',
                    'reference' => $payment['reference'] ?? null,
                    'paid_on' => $payment['paid_on'] ?? today(),
                    'received_by' => auth()->user()?->name,
                    'notes' => $payment['notes'] ?? null,
                ]);
            }

            // Paying reactivates a school that was switched off for non-payment.
            if ($school->status === 'suspended') {
                $school->update(['status' => 'active']);
            }

            return $subscription;
        });
    }

    /**
     * Move the school to another plan straight away (limits change today;
     * dates and money are unchanged -- settle any difference with a payment).
     */
    public static function changePlan(School $school, Plan $plan): ?Subscription
    {
        $current = static::current($school);
        $current?->update(['plan_id' => $plan->getKey()]);

        // Paid-ahead periods move too.
        Subscription::where('school_id', $school->getKey())
            ->where('is_cancelled', false)
            ->where('starts_on', '>', today())
            ->update(['plan_id' => $plan->getKey()]);

        return $current?->fresh('plan');
    }

    public static function extend(School $school, int $days): ?Subscription
    {
        $last = Subscription::where('school_id', $school->getKey())->where('is_cancelled', false)->orderByDesc('ends_on')->first();
        $last?->update(['ends_on' => CarbonImmutable::parse($last->ends_on)->max(CarbonImmutable::yesterday())->addDays($days)]);

        return $last;
    }

    protected static function id(School|int $school): int
    {
        return $school instanceof School ? (int) $school->getKey() : $school;
    }

    // ── Activation codes (pay without visiting the school) ──

    /**
     * Create a code. Pass a school when you already know who paid; leave it
     * null to mint a spare "voucher" ahead of time -- unassigned stock you
     * can keep a list of (e.g. on your phone) and hand one out the moment
     * someone pays, without touching the admin panel. Whoever redeems it
     * first claims it for their school. Either way, redeeming later runs
     * exactly the same renewal renew() would, so payment history and plan
     * limits stay correct.
     *
     * @param  array{amount?: float, method?: string, reference?: ?string, notes?: ?string}|null  $payment
     */
    public static function issueActivationCode(?School $school, Plan $plan, string $cycle, float $amount, ?array $payment = null, mixed $customEndsOn = null, ?string $notes = null, int $validDays = 14): SubscriptionActivationCode
    {
        return SubscriptionActivationCode::create([
            'school_id' => $school?->getKey(),
            'plan_id' => $plan->getKey(),
            'code' => static::generateCode(),
            'cycle' => $cycle,
            'amount' => $amount,
            'payment_amount' => $payment['amount'] ?? 0,
            'payment_method' => $payment['method'] ?? null,
            'payment_reference' => $payment['reference'] ?? null,
            'payment_notes' => $payment['notes'] ?? null,
            'custom_ends_on' => $cycle === 'custom' ? $customEndsOn : null,
            'notes' => $notes,
            'expires_at' => today()->addDays($validDays),
            'created_by' => auth()->user()?->name,
        ]);
    }

    /**
     * A batch of unassigned codes for the same plan/cycle/price -- stock to
     * keep on hand (e.g. a note on your phone) so you can send one out the
     * instant someone pays, before you're back at a computer.
     *
     * @return Collection<int, SubscriptionActivationCode>
     */
    public static function issueStockCodes(Plan $plan, string $cycle, float $amount, int $count, ?array $payment = null, int $validDays = 30): Collection
    {
        return collect(range(1, $count))
            ->map(fn () => static::issueActivationCode(null, $plan, $cycle, $amount, $payment, null, null, $validDays));
    }

    /**
     * A school types its code in on its own Subscription page. Applies the
     * exact renewal the code was issued for and marks it spent. If the
     * code was unassigned stock, this school claims it.
     *
     * @param  ?int  $expectedPlanId  When the school chose a plan card before typing the code, the code must be for that same plan.
     */
    public static function redeemActivationCode(School $school, string $code, ?int $expectedPlanId = null): Subscription
    {
        $code = strtoupper(trim($code));

        $record = SubscriptionActivationCode::with('plan')->where('code', $code)->first();

        if (! $record || ! $record->isRedeemable()) {
            throw new InvalidActivationCode('That code is not valid. Check it and try again, or ask SchoolHub for a new one.');
        }

        if ($record->school_id !== null && (int) $record->school_id !== (int) $school->getKey()) {
            throw new InvalidActivationCode('That code is not valid. Check it and try again, or ask SchoolHub for a new one.');
        }

        if ($expectedPlanId && $record->plan_id !== $expectedPlanId) {
            throw new InvalidActivationCode("That code is for the {$record->plan->name} plan, not the one you selected. Choose {$record->plan->name} instead, or ask SchoolHub for a code for this plan.");
        }

        return DB::transaction(function () use ($school, $record) {
            $subscription = static::renew(
                $school,
                $record->plan,
                $record->cycle,
                $record->amount,
                $record->payment_amount > 0 ? [
                    'amount' => $record->payment_amount,
                    'method' => $record->payment_method ?? 'mobile_money',
                    'reference' => $record->payment_reference,
                    'paid_on' => today(),
                    'notes' => $record->payment_notes,
                ] : null,
                $record->custom_ends_on,
                $record->notes,
            );

            $record->update([
                'school_id' => $record->school_id ?? $school->getKey(),
                'used_at' => now(),
                'used_by' => auth()->user()?->name,
            ]);

            return $subscription;
        });
    }

    /** XXXX-XXXX-XXXX, from a charset without 0/O/1/I/L -- easy to read over the phone. */
    protected static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $raw = collect(range(1, 12))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
            $code = implode('-', str_split($raw, 4));
        } while (SubscriptionActivationCode::where('code', $code)->exists());

        return $code;
    }
}
