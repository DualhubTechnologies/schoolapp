<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_teacher_id')->nullable()
                ->constrained('staff')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['school_class_id', 'name']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
