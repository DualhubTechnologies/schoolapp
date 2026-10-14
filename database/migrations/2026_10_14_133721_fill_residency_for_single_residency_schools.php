<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;

/**
 * Learners already in a boarding-only (or day-only) school who have no
 * residency get the school's one, so boarding fees reach them. Learners
 * with a residency are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        School::query()
            ->whereIn('boarding_type', ['boarding', 'day'])
            ->each(fn (School $school) => $school->fillSingleResidency());
    }

    public function down(): void
    {
        //
    }
};
