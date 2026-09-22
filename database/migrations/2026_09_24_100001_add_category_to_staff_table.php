<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teaching or non-teaching staff. Only teaching staff can be made subject
 * teachers; the split also shows on staff lists and payroll reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('category', 20)->default('teaching')->after('employment_type');
            $table->index(['school_id', 'category']);
        });

        // Existing staff: anyone whose position does not read like a
        // teaching post is marked non-teaching.
        DB::table('staff')
            ->where(fn ($q) => $q->whereNull('position')
                ->orWhere(fn ($q) => $q
                    ->where('position', 'not like', '%teacher%')
                    ->where('position', 'not like', '%tutor%')
                    ->where('position', 'not like', '%lecturer%')
                    ->where('position', 'not like', '%instructor%')
                    ->where('position', 'not like', '%head of department%')
                    ->where('position', 'not like', '%director of studies%')))
            ->update(['category' => 'non_teaching']);
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
