<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SchoolPay\SchoolPayPayments;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * The safety net under SchoolPay's web hook, which sends each payment only
 * once: asks SchoolPay for every school's payments over the last few days
 * and records any SchoolHub has not seen. A school whose SchoolPay details
 * are wrong is skipped, with the reason kept on the school.
 */
class SyncSchoolPay extends Command
{
    protected $signature = 'schoolpay:sync {--days=3 : Days back to check, today included} {--school= : Only this school (id)}';

    protected $description = 'Record SchoolPay payments missed by the web hook';

    public function handle(SchoolPayPayments $payments): int
    {
        $schools = School::query()
            ->where('schoolpay_enabled', true)
            ->when($this->option('school'), fn ($query, $id) => $query->whereKey($id))
            ->get()
            ->filter(fn (School $school): bool => $school->usesSchoolPay());

        foreach ($schools as $school) {
            try {
                $counts = $payments->sync($school, max(1, (int) $this->option('days')));

                $this->line("{$school->name}: {$counts['recorded']} recorded, {$counts['unmatched']} need a learner, {$counts['other_fees']} other fees, {$counts['already']} already in.");
            } catch (RuntimeException $e) {
                $this->warn("{$school->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
