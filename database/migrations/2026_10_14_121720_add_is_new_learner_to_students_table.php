<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A learner who has just joined the school pays its admission fees
 * (admission, development, uniform...) once, in their first term. Before
 * this, a "once" fee went to every learner the first time they were
 * billed, so a school that started on SchoolHub mid-way charged admission
 * to all its continuing learners.
 *
 * Existing learners count as continuing, except those admitted in their
 * school's current term who have not been billed yet: they are the ones
 * the old rule would have charged as new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_new_learner')->default(false)->after('admission_date');
        });

        $currentTerms = DB::table('terms')
            ->where('is_current', true)
            ->whereNotNull('start_date')
            ->get(['school_id', 'start_date']);

        foreach ($currentTerms as $term) {
            DB::table('students')
                ->where('school_id', $term->school_id)
                ->where('admission_date', '>=', $term->start_date)
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('student_charges')
                    ->whereColumn('student_charges.student_id', 'students.id'))
                ->update(['is_new_learner' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_new_learner');
        });
    }
};
