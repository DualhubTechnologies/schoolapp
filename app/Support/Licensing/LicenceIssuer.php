<?php

namespace App\Support\Licensing;

use App\Models\IssuedLicence;
use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Windows app licences on the online server.
 *
 * issue()     records a licence and gives it a short code
 *             (FGDH-FWFH-2342-WETR) for the school to type. Named for a
 *             school, it is signed at once; left open, it is signed for
 *             whichever school enters it first.
 * activate()  what the app calls, once, with the code, its school's
 *             name and code and its computer (Machine): the signed licence
 *             key comes back (LicenceKey), for that computer only, and from
 *             then on the code belongs to that school and computer. Entering
 *             it again there (after reinstalling) gives the same licence; at
 *             another school or computer it is refused (the platform owner
 *             can move it: Windows licences → Move to another computer).
 */
class LicenceIssuer
{
    public const CYCLES = ['trial' => 'Free trial', 'term' => 'One term', 'year' => 'One year'];

    /** A trial runs the first term; a term to the next school term; a year three terms. */
    public static function defaultEnd(CarbonImmutable $starts, string $cycle): CarbonImmutable
    {
        return $cycle === 'trial'
            ? SubscriptionManager::trialEndsOn($starts)
            : SubscriptionManager::periodEnd($starts, $cycle === 'year' ? 'year' : 'term');
    }

    /**
     * @param  array{school_name?: ?string, school_code?: ?string, plan_id: int|string|null, max_students: int|string|null, max_users: int|string|null, cycle: string, starts_on: string, ends_on: string, amount?: int|float|string|null, payment_reference?: ?string, notes?: ?string}  $data
     */
    public function issue(array $data, ?User $by = null): IssuedLicence
    {
        $this->privateKey();

        $plan = filled($data['plan_id'] ?? null) ? Plan::query()->whereKey((int) $data['plan_id'])->first() : null;
        $limit = fn ($value): ?int => $value === null || $value === '' ? null : max(0, (int) $value);
        $schoolName = trim((string) ($data['school_name'] ?? '')) ?: null;
        $schoolCode = strtoupper(trim((string) ($data['school_code'] ?? ''))) ?: null;

        return DB::transaction(function () use ($data, $by, $plan, $limit, $schoolName, $schoolCode): IssuedLicence {
            do {
                $short = ShortCode::generate();
            } while (IssuedLicence::where('short_code', $short)->exists());

            $licence = IssuedLicence::create([
                'licence_no' => IssuedLicence::nextNumber(),
                'short_code' => $short,
                'school_name' => $schoolName,
                'school_code' => $schoolCode,
                'plan_id' => $plan?->getKey(),
                'plan_name' => $plan->name ?? 'Custom',
                'max_students' => $limit($data['max_students'] ?? null),
                'max_users' => $limit($data['max_users'] ?? null),
                'cycle' => $data['cycle'],
                'starts_on' => CarbonImmutable::parse($data['starts_on'])->toDateString(),
                'ends_on' => CarbonImmutable::parse($data['ends_on'])->toDateString(),
                'amount' => (float) ($data['amount'] ?? 0),
                'payment_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'issued_by' => $by?->getKey(),
            ]);

            // Named for a school: signed now, so the long key can be sent
            // too if the school can never get online.
            if ($schoolName && $schoolCode) {
                $licence->update(['key' => $this->sign($licence)]);
            }

            return $licence;
        });
    }

    /**
     * Swap a short code for the school's signed licence key.
     *
     * @throws RuntimeException with a message for the school
     */
    public function activate(string $code, string $schoolName, string $schoolCode, ?string $machine = null): string
    {
        $short = ShortCode::normalise($code);
        $schoolName = trim($schoolName);
        $schoolCode = strtoupper(trim($schoolCode));
        $same = fn (?string $a, string $b): bool => $a !== null && mb_strtolower(preg_replace('/\s+/', ' ', trim($a)) ?? '') === mb_strtolower(preg_replace('/\s+/', ' ', $b) ?? '');

        if (! $short || $schoolName === '' || $schoolCode === '') {
            throw new RuntimeException('That licence code is not valid. Check it and try again.');
        }

        return DB::transaction(function () use ($short, $schoolName, $schoolCode, $same, $machine): string {
            $licence = IssuedLicence::where('short_code', $short)->lockForUpdate()->first();

            if (! $licence) {
                throw new RuntimeException('That licence code is not valid. Check it and try again.');
            }

            if ($licence->school_code !== null && ! ($same($licence->school_code, $schoolCode) && $same($licence->school_name, $schoolName))) {
                throw new RuntimeException($licence->activated_at
                    ? 'This licence code has already been used by another school.'
                    : 'This licence code was made for another school.');
            }

            if (! $licence->school_code) {
                // An open code: it becomes this school's. A trial runs its
                // length from today, the day it is first used.
                $changes = ['school_name' => $schoolName, 'school_code' => $schoolCode];

                if ($licence->cycle === 'trial') {
                    $length = (int) $licence->starts_on->diffInDays($licence->ends_on);
                    $changes['starts_on'] = today()->toDateString();
                    $changes['ends_on'] = today()->addDays($length)->toDateString();
                }

                $licence->update($changes);
            }

            // One computer per code: the first one it is entered on.
            if ($machine !== null && $licence->machine !== null && $licence->machine !== $machine) {
                throw new RuntimeException('This licence code is already in use on another computer. To move it to this computer, ask SchoolHub on '.config('contact.phone').'.');
            }

            if ($machine !== null && $licence->machine === null) {
                $licence->update(['machine' => $machine, 'key' => null]);
            }

            if (! $licence->key) {
                $licence->update(['key' => $this->sign($licence->fresh() ?? $licence)]);
            }

            if (! $licence->activated_at) {
                $licence->update(['activated_at' => now()]);
            }

            return (string) $licence->key;
        });
    }

    /**
     * A linked Windows app's licence from its school's online subscription
     * (sent at every sync), so one payment online covers the computer too:
     * the same plan, limits and paid-until date, trial included. Made for
     * the computer's own school name and code. Null when nothing is in
     * force online, or licences cannot be signed on this server.
     */
    public function forOnlineAccount(School $school, string $schoolName, string $schoolCode, ?string $machine = null): ?string
    {
        $status = SubscriptionManager::status($school);
        $subscription = $status['subscription'];

        if (! in_array($status['state'], ['trial', 'active', 'grace'], true) || ! $subscription || ! $status['ends_on'] || blank(config('licence.private_key'))) {
            return null;
        }

        $plan = $status['plan'];
        $ends = $status['ends_on']->toDateString();

        return LicenceKey::sign([
            'id' => 'ONLINE-'.$school->getKey().'-'.($plan?->getKey() ?? 0).'-'.$ends,
            'school' => trim($schoolName),
            'code' => strtoupper(trim($schoolCode)),
            'plan' => (string) ($plan->name ?? 'Online'),
            'students' => $plan?->max_students,
            'users' => $plan?->max_users,
            'cycle' => $subscription->isTrial() ? 'trial' : (string) $subscription->cycle,
            'starts' => $subscription->starts_on->toDateString(),
            'ends' => $ends,
            'issued' => now()->toIso8601String(),
            'machine' => $machine,
        ], $this->privateKey());
    }

    protected function sign(IssuedLicence $licence): string
    {
        return LicenceKey::sign([
            'id' => $licence->licence_no,
            'school' => (string) $licence->school_name,
            'code' => (string) $licence->school_code,
            'plan' => $licence->plan_name,
            'students' => $licence->max_students,
            'users' => $licence->max_users,
            'cycle' => $licence->cycle,
            'starts' => $licence->starts_on->toDateString(),
            'ends' => $licence->ends_on->toDateString(),
            'issued' => now()->toIso8601String(),
            'machine' => $licence->machine,
        ], $this->privateKey());
    }

    protected function privateKey(): string
    {
        $privateKey = (string) config('licence.private_key');

        if ($privateKey === '') {
            throw new RuntimeException('Licences cannot be issued yet: run php artisan licence:keygen on the server first.');
        }

        return $privateKey;
    }
}
