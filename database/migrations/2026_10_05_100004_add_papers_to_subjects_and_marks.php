<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects sat as more than one paper (A-Level Paper 1, Paper 2...): how
 * many papers a subject has, and which paper a mark is for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedTinyInteger('papers')->default(1)->after('category');
        });

        Schema::table('marks', function (Blueprint $table) {
            $table->unsignedTinyInteger('paper')->default(1)->after('subject_id');
            $table->unique(['assessment_id', 'student_id', 'subject_id', 'paper'], 'marks_assessment_student_subject_paper_unique');
        });

        Schema::table('marks', function (Blueprint $table) {
            $table->dropUnique(['assessment_id', 'student_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->unique(['assessment_id', 'student_id', 'subject_id']);
            $table->dropUnique('marks_assessment_student_subject_paper_unique');
            $table->dropColumn('paper');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('papers');
        });
    }
};
