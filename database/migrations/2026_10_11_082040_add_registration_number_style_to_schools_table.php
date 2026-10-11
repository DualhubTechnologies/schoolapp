<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a school's registration (admission) numbers look: initials, year
 * of admission and a serial that starts again each year, e.g.
 * GHS/2026/0001 (App\Support\AdmissionNumbers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('admission_no_style', 20)->default('initials_year')->after('logo');
            $table->string('admission_no_initials', 10)->nullable()->after('admission_no_style');
            $table->unsignedInteger('admission_no_start')->nullable()->after('admission_no_initials');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['admission_no_style', 'admission_no_initials', 'admission_no_start']);
        });
    }
};
