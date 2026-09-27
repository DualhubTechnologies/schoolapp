<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->string('name', 100)->after('school_class_id');
            $table->string('frequency', 30)->default('per_term')->after('name');
            $table->string('applies_to', 30)->default('all')->after('frequency');
        });

        // Shorten term/academic_year too, so the composite unique key fits
        // MySQL's 3072-byte limit under utf8mb4.
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->string('term', 30)->change();
            $table->string('academic_year', 9)->change();
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropUnique('fee_struct_unique');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->unique(
                ['school_id', 'school_class_id', 'name', 'term', 'academic_year'],
                'fee_struct_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropUnique('fee_struct_unique');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->unique(
                ['school_id', 'school_class_id', 'term', 'academic_year'],
                'fee_struct_unique'
            );
            $table->dropColumn(['name', 'frequency', 'applies_to']);
        });
    }
};
