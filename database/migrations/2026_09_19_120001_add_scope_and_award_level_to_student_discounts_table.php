<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_discounts', function (Blueprint $table) {
            // How long the award runs: term | year | ongoing
            //
            // Ugandan bursaries are typically granted for one year and
            // renewed only if grades, conduct and need still hold. An
            // award with no end quietly keeps discounting after it has
            // lapsed, which costs the school money nobody notices.
            $table->string('scope')->default('term')->after('term_id');

            // Set when scope = year.
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('scope')
                ->constrained()
                ->nullOnDelete();

            // full | half | partial
            // Named award levels schools advertise. 'partial' means the
            // value was entered by hand rather than implied by the level.
            $table->string('award_level')->nullable()->after('reason');

            $table->index(['school_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::table('student_discounts', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['scope', 'academic_year_id', 'award_level']);
        });
    }
};
