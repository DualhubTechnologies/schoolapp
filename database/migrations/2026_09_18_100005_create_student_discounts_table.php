<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Null = applies to every fee. Set = that one fee only.
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();

            // Null = ongoing. Set = that term only.
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reason');          // scholarship, staff child, sibling...
            $table->string('type');            // percentage | fixed
            $table->decimal('value', 14, 2);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'student_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_discounts');
    }
};
