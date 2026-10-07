<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An exam can be for some classes and subjects only, e.g. a continuous
 * assessment given in S.2 Biology. Empty means every class and subject,
 * as before (BOT, MOT, End of Term).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->json('class_ids')->nullable()->after('curriculum');
            $table->json('subject_ids')->nullable()->after('class_ids');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['class_ids', 'subject_ids']);
        });
    }
};
