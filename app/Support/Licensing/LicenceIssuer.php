<?php

namespace App\Support\Licensing;

use App\Models\IssuedLicence;
use App\Models\Plan;
use App\Models\User;
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
 * activate()  what the app calls, once, with the code and its school's
 *             name and code: the signed licence key comes back (LicenceKey),
 *             and from then on the code belongs to that school. Entering it
 *             again at the same school (after reinstalling) gives the same
 *             licence; at another school it is refused.
 */
class LicenceIssuer
{
    public const CYCLES = ['trial' => 'Free trial', 'term' => 'One term', 'year' => 'One year'];

    /** A trial runs the trial days; a term four months; a year twelve. */
    public static function defaultEnd(CarbonImmutable $starts, string $cycle): CarbonImmutable
    {
        return match ($cycle) {
            'trial' => $starts->addDays(max(1, (int) config('subscriptions.trial_days', 30)) - 1),
            'year' => $starts->addMonthsNoOverflow(12)->subDay(),
            default => $starts->addMonthsNoOverflow(4)->subDay(),
        };
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
    public function activate(string $code, string $schoolName, string $schoolCode): string
    {
        $short = ShortCode::normalise($code);
        $schoolName = trim($schoolName);
        $schoolCode = strtoupper(trim($schoolCode));
        $same = fn (?string $a, string $b): bool => $a !== null && mb_strtolower(preg_replace('/\s+/', ' ', trim($a)) ?? '') === mb_strtolower(preg_replace('/\s+/', ' ', $b) ?? '');

        if (! $short || $schoolName === '' || $schoolCode === '') {
            throw new RuntimeException('That licence code is not valid. Check it and try again.');
        }

        return DB::transaction(function () use ($short, $schoolName, $schoolCode, $same): string {
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

            if (! $licence->key) {
                $licence->update(['key' => $this->sign($licence->fresh() ?? $licence)]);
            }

            if (! $licence->activated_at) {
                $licence->update(['activated_at' => now()]);
            }

            return (string) $licence->key;
        });
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
