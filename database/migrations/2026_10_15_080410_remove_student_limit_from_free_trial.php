<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The free trial takes any number of learners, so a school can bring its
 * whole register in while it tries SchoolHub (it was capped at 1,000).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->where('is_trial', true)->update(['max_students' => null]);
    }

    public function down(): void
    {
        DB::table('plans')->where('is_trial', true)->update(['max_students' => 1000]);
    }
};
