<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of year-end promotions: one row per student per run, with
 * enough of the "before" state to undo the run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch');
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);                 // promote | repeat | complete | leave
            $table->foreignId('from_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('from_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('from_status', 20);
            $table->foreignId('from_combination_id')->nullable()->constrained('combinations')->nullOnDelete();
            $table->foreignId('to_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('to_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('performed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'batch']);
            $table->index(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
