<?php

namespace App\Services\Subscriptions;

use App\Exceptions\PlanLimitReached;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
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
                "Your {$plan->name} plan allows " . number_format($plan->max_students) . ' active students and the school has reached it. '
                . 'Mark students who have left as Withdrawn/Transferred/Completed, or ask for a bigger plan on the Subscription page.'
            );
        }
    }

    public static function ensureRoomForUsers(School|int $school, int $adding = 1): void
    {
        $room = static::roomForUsers($school);

        if ($room !== null && $room < $adding) {
            $plan = static::current($school)->plan;

            throw new PlanLimitReached(
                "Your {$plan->name} plan allows " . number_format($plan->max_users) . ' staff logins and the school has reached it. '
                . 'Delete logins that are no longer used, or ask for a bigger plan on the Subscription page.'
            );
        }
    }

    // ── Changes (platform owner) ──

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
     * never loses days; a trial is replaced from today.
     *
     * @param  array{amount?: float, method?: string, reference?: ?string, paid_on?: mixed, notes?: ?string}|null  $payment
     */
    public static function renew(School $school, Plan $plan, string $cycle, float $amount, ?array $payment = null, mixed $customEndsOn = null, ?string $notes = null): Subscription
    {
        return DB::transaction(function () use ($school, $plan, $cycle, $amount, $payment, $customEndsOn, $notes) {
            $current = static::current($school);
            $lastEnd = Subscription::where('school_id', $school->getKey())
                ->where('is_cancelled', false)
                ->where('cycle', '!=', 'trial')
                ->max('ends_on');

            $start = CarbonImmutable::today();

            if ($lastEnd && CarbonImmutable::parse($lastEnd)->gte($start)) {
                $start = CarbonImmutable::parse($lastEnd)->addDay();
            }

            $end = $cycle === 'custom' && $customEndsOn
                ? CarbonImmutable::parse($customEndsOn)
                : $start->addMonths((int) config("subscriptions.cycle_months.{$cycle}", 4))->subDay();

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
}
