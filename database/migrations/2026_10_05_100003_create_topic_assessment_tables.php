<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NCDC topic assessment for the new lower-secondary curriculum: each
 * subject's syllabus topics per class, and each learner's level (0-3)
 * for a topic, recorded in the term it was assessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('class_number');     // S.1 = 1 ... S.4 = 4
            $table->string('code', 20)->nullable();          // T1, T2...
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'subject_id', 'class_number']);
        });

        Schema::create('topic_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('syllabus_topic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');           // 0-3
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'syllabus_topic_id']);
            $table->index(['term_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_scores');
        Schema::dropIfExists('syllabus_topics');
    }
};
