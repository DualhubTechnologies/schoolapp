<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sick bay register: each visit by a learner or member of staff, what
 * was wrong, what was given and what happened next; and, if the school
 * keeps it, the sick bay's medicine stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_bay_medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                   // e.g. Paracetamol 500mg
            $table->string('unit', 30)->default('tablets');
            $table->integer('stock')->default(0);
            $table->integer('reorder_level')->nullable();             // warn at or below this
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('sick_bay_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('visited_at');
            $table->string('complaint');
            $table->decimal('temperature', 4, 1)->nullable();          // °C
            $table->string('diagnosis')->nullable();
            $table->text('treatment')->nullable();                      // what was done or given, in words
            $table->string('outcome', 30)->default('returned_to_class'); // SickBayVisit::OUTCOMES
            $table->string('referred_to')->nullable();                  // hospital or clinic
            $table->dateTime('discharged_at')->nullable();              // left the sick bay after resting
            $table->dateTime('parent_notified_at')->nullable();
            $table->string('attended_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'visited_at']);
        });

        Schema::create('sick_bay_dispensed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sick_bay_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sick_bay_medicine_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('dosage')->nullable();                      // e.g. 2 tabs, 3 times a day
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_bay_dispensed');
        Schema::dropIfExists('sick_bay_visits');
        Schema::dropIfExists('sick_bay_medicines');
    }
};
