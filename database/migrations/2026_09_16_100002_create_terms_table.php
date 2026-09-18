<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();

            // e.g. "Term 1". The school names its own terms; nothing here
            // assumes three of them.
            $table->string('name');

            // Order within the year. This is what makes carry-forward
            // possible: to find a student's arrears the system has to know
            // which term came before this one.
            $table->unsignedSmallInteger('sequence');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Exactly one term per school carries this flag. The model
            // enforces that on save.
            $table->boolean('is_current')->default(false);

            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
            $table->index(['school_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
