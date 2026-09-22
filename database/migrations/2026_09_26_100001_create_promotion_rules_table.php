<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school's promotion rules per curriculum (primary, O-Level, A-Level),
 * and the recommendation recorded with each promotion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('curriculum', 20);
            $table->string('basis', 20)->default('annual');         // annual | final_term
            $table->decimal('min_average', 5, 2)->default(40);     // % to be promoted
            $table->decimal('probation_margin', 5, 2)->default(5); // % below the line = probation
            $table->json('required_subjects')->nullable();          // names to match, e.g. ["English","Mathematics"]
            $table->decimal('subject_pass_mark', 5, 2)->default(40);
            $table->unsignedTinyInteger('auto_promote_upto')->nullable(); // e.g. 3 = P1–P3 promote automatically
            $table->unsignedTinyInteger('min_points')->nullable();        // A-Level
            $table->timestamps();

            $table->unique(['school_id', 'curriculum']);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('recommendation', 20)->nullable()->after('action'); // promote | probation | repeat | no_results
            $table->string('reason')->nullable()->after('recommendation');
            $table->decimal('average', 5, 2)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['recommendation', 'reason', 'average']);
        });
        Schema::dropIfExists('promotion_rules');
    }
};
