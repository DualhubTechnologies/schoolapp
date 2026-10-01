<?php

namespace App\Support\Licensing;

use App\Models\LicenceKeyRecord;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
 *   no key yet          locked: even the free trial needs a licence (a trial
 *                       code from SchoolHub), so every school is known
 *   a trial key         the trial, with the trial plan's limits
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
     * What the school types on its Licence page: a short code
     * (FGDH-FWFH-2342-WETR), swapped once, online, for the signed licence;
     * or, for a school that can never get online, the long key itself.
     * Throws with a message for the school.
     */
    public static function enter(School $school, string $typed): LicenceKey
    {
        if (str_starts_with(trim($typed), LicenceKey::PREFIX.'.')) {
            return static::activate($school, $typed);
        }

        if (! ShortCode::normalise($typed)) {
            throw new RuntimeException('A licence code has 16 letters and numbers, like ABCD-EFGH-2345-JKLM. Check it and try again.');
        }

        try {
            $response = Http::acceptJson()->timeout(20)->post((string) config('licence.activation_url'), [
                'code' => $typed,
                'school_name' => (string) $school->name,
                'school_code' => (string) $school->unique_code,
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Entering a licence code needs the internet for a moment. Connect this computer (phone data is enough), then try again. After that SchoolHub works offline.');
        }

        if ($response->status() === 422 || $response->status() === 429) {
            throw new RuntimeException((string) ($response->json('message') ?: 'That licence code was not accepted. Check it and try again.'));
        }

        $key = $response->json('key');

        if (! $response->successful() || ! is_string($key)) {
            throw new RuntimeException('SchoolHub could not be reached just now. Please try again in a few minutes.');
        }

        return static::activate($school, $key);
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
            throw new RuntimeException('This licence has already been entered.');
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

        // No key yet: nothing in force (even the trial needs a licence).
        return null;
    }

    /** The last day paid for: the latest key's end; none without a key. */
    public static function endsOn(School $school): ?CarbonImmutable
    {
        $ends = array_map(fn (LicenceKey $k): CarbonImmutable => $k->endsOn(), static::keys($school));

        return $ends === [] ? null : max($ends);
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
