<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academic management, examinations and grading for Ugandan schools.
 *
 *   class_levels.curriculum  which curriculum a level follows: nursery,
 *                            primary, o_level (new lower-secondary
 *                            curriculum) or a_level
 *   subjects                 what the school teaches, per curriculum
 *   class_subject            which subjects each class takes, whether
 *                            compulsory there, and who teaches it
 *   combinations             A-Level combinations (PCM, HEG, ...)
 *   student_subject          a student's electives / subsidiary choice
 *   grading_scales/bands     mark → grade tables, editable per school
 *   assessments              exams and continuous assessment per term
 *   marks                    one score per student, subject, assessment
 *   term_reports             report-card comments per student per term
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_levels', function (Blueprint $table) {
            $table->string('curriculum', 20)->nullable()->after('name');
        });

        // Existing levels: recognise the defaults by name.
        foreach (DB::table('class_levels')->get(['id', 'name']) as $level) {
            $name = strtolower($level->name);
            $curriculum = match (true) {
                str_contains($name, 'a-level'), str_contains($name, 'a level'), str_contains($name, 'advanced') => 'a_level',
                str_contains($name, 'o-level'), str_contains($name, 'o level'), str_contains($name, 'ordinary'), str_contains($name, 'lower secondary') => 'o_level',
                str_contains($name, 'nursery'), str_contains($name, 'kindergarten'), str_contains($name, 'ecd') => 'nursery',
                str_contains($name, 'primary') => 'primary',
                default => null,
            };
            DB::table('class_levels')->where('id', $level->id)->update(['curriculum' => $curriculum]);
        }

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('curriculum', 20);             // primary | o_level | a_level | nursery
            $table->string('name');
            $table->string('short_name', 20)->nullable(); // for broadsheet headings: ENG, MTC
            $table->string('code', 20)->nullable();       // UNEB code, if the school uses it
            // core       counts towards the primary aggregate (Eng, Maths, Sci, SST)
            // principal  A-Level principal subject (A–F, 6–0 points)
            // subsidiary A-Level subsidiary (GP, Sub-Maths, Sub-ICT; 1 point)
            // standard   everything else
            $table->string('category', 20)->default('standard');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'curriculum', 'name']);
        });

        Schema::create('class_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_compulsory')->default(true);
            $table->foreignId('teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id']);
        });

        Schema::create('combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);                      // PCM
            $table->string('description')->nullable();       // Physics, Chemistry, Mathematics
            $table->foreignId('subsidiary_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('combination_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unique(['combination_id', 'subject_id']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('combination_id')->nullable()->after('section_id')->constrained()->nullOnDelete();
        });

        Schema::create('student_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'subject_id']);
        });

        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('curriculum', 20);
            $table->string('purpose', 20)->default('subject'); // subject | principal | subsidiary
            $table->string('name');
            $table->timestamps();

            $table->unique(['school_id', 'curriculum', 'purpose']);
        });

        Schema::create('grading_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 10);                   // D1, A, ...
            $table->decimal('min_score', 5, 2);            // percentage, inclusive
            $table->decimal('max_score', 5, 2);
            $table->decimal('value', 5, 2)->default(0);    // aggregate value or points
            $table->string('descriptor')->nullable();      // Distinction, Exceptional, ...
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('name');                         // Mid-Term Examination
            $table->string('type', 20);                     // bot | mot | eot | ca | other
            $table->string('curriculum', 20)->nullable();   // null = every class
            $table->decimal('max_score', 6, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(0);    // % of the term result
            $table->date('held_on')->nullable();
            $table->string('status', 20)->default('open');  // open | locked
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('comment')->nullable();
            $table->string('entered_by')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id', 'subject_id']);
            $table->index(['assessment_id', 'subject_id']);
        });

        Schema::create('term_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->text('class_teacher_comment')->nullable();
            $table->text('head_teacher_comment')->nullable();
            $table->string('conduct', 30)->nullable();
            $table->unsignedSmallInteger('days_present')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_reports');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('grading_bands');
        Schema::dropIfExists('grading_scales');
        Schema::dropIfExists('student_subject');
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combination_id');
        });
        Schema::dropIfExists('combination_subject');
        Schema::dropIfExists('combinations');
        Schema::dropIfExists('class_subject');
        Schema::dropIfExists('subjects');
        Schema::table('class_levels', function (Blueprint $table) {
            $table->dropColumn('curriculum');
        });
    }
};
