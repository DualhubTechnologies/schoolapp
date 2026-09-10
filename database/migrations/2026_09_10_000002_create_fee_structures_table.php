<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('term');            // e.g. 'Term 1'
            $table->string('academic_year');   // e.g. '2026'
            $table->decimal('amount', 12, 2);  // fees for this class/term/year
            $table->string('currency')->default('UGX');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One fee row per class + term + year within a school
            $table->unique(['school_id', 'school_class_id', 'term', 'academic_year'], 'fee_struct_unique');
            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
