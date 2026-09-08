<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()
                ->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('admission_no');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->date('admission_date')->nullable();
            $table->string('photo')->nullable();
            $table->text('address')->nullable();
            $table->text('medical_notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'admission_no']);
            $table->index(['school_id', 'status']);
            $table->index(['school_class_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};