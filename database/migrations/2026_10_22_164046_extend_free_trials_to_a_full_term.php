<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The free trial is now a full term (4 months) instead of 30 days. Schools
 * on a trial today get the same: their trial runs 4 months from the day
 * it started. A trial already longer than that is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscriptions')
            ->where('cycle', 'trial')
            ->where('is_cancelled', false)
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->orderBy('id')
            ->each(function (object $trial): void {
                $fullTerm = CarbonImmutable::parse($trial->starts_on)->addMonthsNoOverflow(4)->subDay();

                if ($fullTerm->gt(CarbonImmutable::parse($trial->ends_on))) {
                    DB::table('subscriptions')->where('id', $trial->id)->update(['ends_on' => $fullTerm->toDateString()]);
                }
            });
    }

    public function down(): void
    {
        // Trials are not shortened again.
    }
};
