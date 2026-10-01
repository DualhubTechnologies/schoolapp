<?php

namespace App\Support\Licensing;

use App\Models\LicenceKeyRecord;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Where the Windows app's school stands, from the licence keys it has
 * entered (LicenceKey), never from dates stored in the database: the
 * database is a file on the school's computer, a signed key is not
 * something it can change.
 *
 * Gives SubscriptionManager the same answer it gives online (state, plan,
 * end date, grace...), so locking, limits and banners work unchanged:
 *
 *   no key yet          the free trial, counted from when the school was set up
 *   a key in force      active, with the key's plan limits
 *   keys paid ahead     the end date is the latest key's
 *   ended               grace days, then locked until a new key is entered
 *   clock set back      locked until the computer's date is right again
 */
class DesktopLicence
{
    /** app_state key: the latest time the app has seen. */
    public const LAST_SEEN = 'licence.last_seen';

    /**
     * The school's valid keys: signed by SchoolHub and made for this school.
     *
     * @return list<LicenceKey>
     */
    public static function keys(School $school): array
    {
        $public = (string) config('licence.public_key');
        $keys = [];

        foreach (LicenceKeyRecord::query()->orderBy('id')->pluck('key') as $text) {
            $licence = LicenceKey::verify((string) $text, $public);

            if ($licence && $licence->isFor((string) $school->name, (string) $school->unique_code)) {
                $keys[] = $licence;
            }
        }

        return $keys;
    }

    /**
     * Check a key and keep it. Throws with a message for the school when
     * the key is wrong, for another school, or already entered.
     */
    public static function activate(School $school, string $key): LicenceKey
    {
        $licence = LicenceKey::verify($key, (string) config('licence.public_key'));

        if (! $licence) {
            throw new RuntimeException('This licence key is not valid. Check that it was copied in full, or ask SchoolHub to send it again.');
        }

        if (! $licence->isFor((string) $school->name, (string) $school->unique_code)) {
            throw new RuntimeException("This licence is for \"{$licence->details['school']}\" ({$licence->details['code']}), not this school. Ask SchoolHub for this school's key.");
        }

        if (LicenceKeyRecord::where('licence_no', $licence->details['id'])->exists()) {
            throw new RuntimeException('This licence key has already been entered.');
        }

        LicenceKeyRecord::create([
            'licence_no' => $licence->details['id'],
            'key' => trim($key),
            'starts_on' => $licence->startsOn()->toDateString(),
            'ends_on' => $licence->endsOn()->toDateString(),
        ]);

        return $licence;
    }

    /**
     * The same shape as SubscriptionManager::status(), worked out from the keys.
     *
     * @return array{state: string, subscription: ?Subscription, plan: ?Plan, ends_on: ?CarbonImmutable, days_left: ?int, grace_ends_on: ?CarbonImmutable, expiring: bool}
     */
    public static function status(?School $school): array
    {
        $today = CarbonImmutable::today();
        $none = ['state' => 'none', 'subscription' => null, 'plan' => null, 'ends_on' => null, 'days_left' => null, 'grace_ends_on' => null, 'expiring' => false];

        if (! $school) {
            return $none;
        }

        $subscription = static::currentPeriod($school);
        $endsOn = static::endsOn($school);
        $graceEnds = $endsOn?->addDays((int) config('subscriptions.grace_days', 14));
        $daysLeft = $endsOn ? (int) $today->diffInDays($endsOn, false) : null;

        $state = match (true) {
            static::clockSetBack() => 'clock',
            ! $subscription || ! $endsOn => 'none',
            $today->lte($endsOn) => $subscription->isTrial() ? 'trial' : 'active',
            $today->lte($graceEnds) => 'grace',
            default => 'expired',
        };

        return [
            'state' => $state,
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'ends_on' => $endsOn,
            'days_left' => $daysLeft,
            'grace_ends_on' => $graceEnds,
            'expiring' => in_array($state, ['trial', 'active'], true) && $daysLeft !== null && $daysLeft <= (int) config('subscriptions.warn_days', 14),
        ];
    }

    /**
     * The period in force (or the last one, once it has ended), as an
     * unsaved Subscription with its plan, so code written for the online
     * subscription reads it the same way.
     */
    public static function currentPeriod(School $school): ?Subscription
    {
        $today = CarbonImmutable::today();
        $started = array_values(array_filter(static::keys($school), fn (LicenceKey $k): bool => $k->startsOn()->lte($today)));
        usort($started, fn (LicenceKey $a, LicenceKey $b): int => $b->startsOn() <=> $a->startsOn());
        $licence = $started[0] ?? null;

        if ($licence) {
            $plan = new Plan([
                'name' => $licence->details['plan'],
                'max_students' => $licence->details['students'],
                'max_users' => $licence->details['users'],
                'is_trial' => false,
                'is_active' => true,
            ]);
            $period = new Subscription([
                'school_id' => $school->getKey(),
                'cycle' => $licence->details['cycle'],
                'starts_on' => $licence->startsOn(),
                'ends_on' => $licence->endsOn(),
                'notes' => 'Licence '.$licence->details['id'],
            ]);

            return $period->setRelation('plan', $plan);
        }

        // No key yet: the free trial, from the day the school was set up.
        if (static::keys($school) !== [] || ! $school->created_at) {
            return null;
        }

        $trialPlan = Plan::where('is_trial', true)->orderBy('sort_order')->first() ?? new Plan(['name' => 'Free Trial', 'max_students' => 1000, 'max_users' => 10, 'is_trial' => true]);
        $starts = CarbonImmutable::parse($school->created_at)->startOfDay();

        return (new Subscription([
            'school_id' => $school->getKey(),
            'cycle' => 'trial',
            'starts_on' => $starts,
            'ends_on' => static::trialEndsOn($school),
        ]))->setRelation('plan', $trialPlan);
    }

    /** The last day paid for: the latest key's end, or the trial's. */
    public static function endsOn(School $school): ?CarbonImmutable
    {
        $ends = array_map(fn (LicenceKey $k): CarbonImmutable => $k->endsOn(), static::keys($school));

        if ($ends !== []) {
            return max($ends);
        }

        return $school->created_at ? static::trialEndsOn($school) : null;
    }

    protected static function trialEndsOn(School $school): CarbonImmutable
    {
        return CarbonImmutable::parse($school->created_at)->startOfDay()->addDays(max(1, (int) config('subscriptions.trial_days', 30)) - 1);
    }

    /**
     * Whether the computer's clock has been set back: today is well before
     * the latest time the app has seen. That time comes from the app's own
     * note and from the newest entry in the activity log, so wiping one
     * does not hide it. Otherwise the note is moved forward to now.
     */
    public static function clockSetBack(): bool
    {
        $now = CarbonImmutable::now();
        $seen = static::lastSeen();
        $tolerance = (int) config('licence.clock_tolerance_hours', 24);

        if ($seen && $now->lt($seen->subHours($tolerance))) {
            return true;
        }

        if (! $seen || $now->gt($seen->addHour())) {
            DB::table('app_state')->updateOrInsert(['key' => self::LAST_SEEN], ['value' => $now->toIso8601String(), 'updated_at' => $now]);
        }

        return false;
    }

    protected static function lastSeen(): ?CarbonImmutable
    {
        $noted = DB::table('app_state')->where('key', self::LAST_SEEN)->value('value');
        $logged = Schema::hasTable('activity_log') ? DB::table('activity_log')->max('created_at') : null;

        $times = array_filter([
            $noted ? CarbonImmutable::parse((string) $noted) : null,
            $logged ? CarbonImmutable::parse((string) $logged) : null,
        ]);

        return $times === [] ? null : max($times);
    }
}
