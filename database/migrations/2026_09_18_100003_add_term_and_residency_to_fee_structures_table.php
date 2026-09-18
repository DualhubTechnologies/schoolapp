<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original table had a unique index on
        // school_id + school_class_id + term + academic_year, which makes
        // two fees for the same class and term impossible -- so tuition AND
        // boarding for S4 Term 1 could never coexist. Drop it if present.
        try {
            Schema::table('fee_structures', function (Blueprint $table) {
                $table->dropUnique('fee_struct_unique');
            });
        } catch (\Throwable $e) {
            // Already dropped by an earlier migration. Nothing to do.
        }

        Schema::table('fee_structures', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_structures', 'term_id')) {
                // Null = not tied to a term (admission, one-off charges).
                $table->foreignId('term_id')
                    ->nullable()
                    ->after('school_class_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            // Null = every student in the class pays it.
            // Set  = only students of that residency pay it.
            //
            // This is what lets one design cover both ways schools price:
            // a single tuition plus a boarding-only charge, OR separate
            // day and boarding tuition as two rows.
            $table->foreignId('residency_type_id')
                ->nullable()
                ->after('term_id')
                ->constrained()
                ->nullOnDelete();
        });

        // The text term / academic_year columns are superseded by term_id.
        // Keeping them would leave two sources of truth for one fact.
        Schema::table('fee_structures', function (Blueprint $table) {
            foreach (['term', 'academic_year'] as $column) {
                if (Schema::hasColumn('fee_structures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropForeign(['residency_type_id']);
            $table->dropColumn('residency_type_id');
            $table->string('term')->nullable();
            $table->string('academic_year')->nullable();
        });
    }
};
