<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each mark sheet (one exam, one class, one subject) has got: open
 * while the teacher enters marks, submitted to the Director of Studies,
 * then approved -- after which nobody changes it unless it is reopened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mark_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('open');  // open | submitted | approved
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('returned_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'school_class_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mark_sheets');
    }
};
