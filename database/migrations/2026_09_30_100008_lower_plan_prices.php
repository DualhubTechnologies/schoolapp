<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan prices cut by about 20% to attract more schools. The yearly price
 * stays at three terms less 10%. Existing subscriptions keep the amount
 * they were bought at; the new prices apply to new payments and renewals.
 */
return new class extends Migration
{
    /** slug => [new per term, new per year, old per term, old per year] */
    private const PRICES = [
        'starter' => [130_000, 351_000, 160_000, 432_000],
        'standard' => [240_000, 648_000, 300_000, 810_000],
        'premium' => [400_000, 1_080_000, 500_000, 1_350_000],
        'enterprise' => [720_000, 1_944_000, 900_000, 2_430_000],
    ];

    public function up(): void
    {
        foreach (self::PRICES as $slug => [$term, $year]) {
            DB::table('plans')->where('slug', $slug)->update([
                'price_per_term' => $term,
                'price_per_year' => $year,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::PRICES as $slug => [, , $term, $year]) {
            DB::table('plans')->where('slug', $slug)->update([
                'price_per_term' => $term,
                'price_per_year' => $year,
                'updated_at' => now(),
            ]);
        }
    }
};
