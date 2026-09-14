<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('houses', function (Blueprint $table) {
            $table->id();

            // Multi-tenancy: houses belong to one school, same as every
            // other operational record in the system.
            $table->foreignId('school_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Two schools may both have a "Red House"; one school may not
            // have two.
            $table->unique(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('houses');
    }
};
