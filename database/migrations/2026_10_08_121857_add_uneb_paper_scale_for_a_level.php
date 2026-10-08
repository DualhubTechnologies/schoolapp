<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A-Level is graded as UNEB grades UACE: every paper D1–F9 (D1 from 85%),
 * then the principal grade from the paper grades. Each school already set
 * up for A-Level gets the paper scale; its old principal and subsidiary
 * percentage scales are no longer used.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schools = DB::table('grading_scales')->where('curriculum', 'a_level')->distinct()->pluck('school_id');

        foreach ($schools as $schoolId) {
            if (DB::table('grading_scales')->where(['school_id' => $schoolId, 'curriculum' => 'a_level', 'purpose' => 'paper'])->exists()) {
                continue;
            }

            $scaleId = DB::table('grading_scales')->insertGetId([
                'school_id' => $schoolId,
                'curriculum' => 'a_level',
                'purpose' => 'paper',
                'name' => 'A-Level — A-Level paper grades (UNEB)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (config('academics.grading.a_level.paper') as $i => [$grade, $min, $max, $value, $descriptor]) {
                DB::table('grading_bands')->insert([
                    'grading_scale_id' => $scaleId,
                    'grade' => $grade,
                    'min_score' => $min,
                    'max_score' => $max,
                    'value' => $value,
                    'descriptor' => $descriptor,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('grading_scales')->where('curriculum', 'a_level')->where('purpose', 'paper')->delete();
    }
};
