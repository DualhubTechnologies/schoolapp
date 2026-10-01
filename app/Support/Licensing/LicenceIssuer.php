<?php

namespace App\Support\Licensing;

use App\Models\IssuedLicence;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Issues a Windows app licence on the online server: signs the key with
 * the private key (config licence.private_key) and records it.
 */
class LicenceIssuer
{
    /** A term licence runs four months; a year licence twelve. */
    public static function defaultEnd(CarbonImmutable $starts, string $cycle): CarbonImmutable
    {
        return $starts->addMonthsNoOverflow($cycle === 'year' ? 12 : 4)->subDay();
    }

    /**
     * @param  array{school_name: string, school_code: string, plan_id: int|string|null, max_students: int|string|null, max_users: int|string|null, cycle: string, starts_on: string, ends_on: string, amount?: int|float|string|null, payment_reference?: ?string, notes?: ?string}  $data
     */
    public function issue(array $data, ?User $by = null): IssuedLicence
    {
        $privateKey = (string) config('licence.private_key');

        if ($privateKey === '') {
            throw new \RuntimeException('Licences cannot be issued yet: run php artisan licence:keygen on the server first.');
        }

        $plan = filled($data['plan_id'] ?? null) ? Plan::find($data['plan_id']) : null;
        $limit = fn ($value): ?int => $value === null || $value === '' ? null : max(0, (int) $value);

        return DB::transaction(function () use ($data, $by, $plan, $limit, $privateKey): IssuedLicence {
            $number = IssuedLicence::nextNumber();
            $details = [
                'id' => $number,
                'school' => trim($data['school_name']),
                'code' => strtoupper(trim($data['school_code'])),
                'plan' => $plan->name ?? 'Custom',
                'students' => $limit($data['max_students'] ?? null),
                'users' => $limit($data['max_users'] ?? null),
                'cycle' => $data['cycle'],
                'starts' => CarbonImmutable::parse($data['starts_on'])->toDateString(),
                'ends' => CarbonImmutable::parse($data['ends_on'])->toDateString(),
                'issued' => now()->toIso8601String(),
            ];

            return IssuedLicence::create([
                'licence_no' => $number,
                'school_name' => $details['school'],
                'school_code' => $details['code'],
                'plan_id' => $plan?->getKey(),
                'plan_name' => $details['plan'],
                'max_students' => $details['students'],
                'max_users' => $details['users'],
                'cycle' => $details['cycle'],
                'starts_on' => $details['starts'],
                'ends_on' => $details['ends'],
                'amount' => (float) ($data['amount'] ?? 0),
                'payment_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'key' => LicenceKey::sign($details, $privateKey),
                'issued_by' => $by?->getKey(),
            ]);
        });
    }
}
