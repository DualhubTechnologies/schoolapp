<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // e.g. "2026" or "2026/2027" — the school decides the format.
            $table->string('name');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Exactly one year per school carries this flag. The model
            // enforces that on save.
            $table->boolean('is_current')->default(false);

            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
