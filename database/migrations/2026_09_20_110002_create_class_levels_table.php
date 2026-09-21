<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // O-Level, A-Level for a secondary school; Nursery, Primary for
            // a primary one. Seeded from the school's type on creation, but
            // editable -- a school that words these differently can.
            //
            // No section heading here: the school is one type or the other,
            // so the type already says which section these belong to.
            $table->string('name');

            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_levels');
    }
};
