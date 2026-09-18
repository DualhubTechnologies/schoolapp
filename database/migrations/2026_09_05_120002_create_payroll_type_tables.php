<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');            // e.g. Housing, Transport, Lunch, Medical
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');            // e.g. NSSF Employee, PAYE, Staff Loan, Welfare
            $table->boolean('is_statutory')->default(false);   // true for NSSF, PAYE
            $table->enum('calculation_method', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('default_rate', 8, 4)->nullable();  // e.g. 5.0000 for NSSF 5%
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('paye_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->string('country', 3);       // UG, KE, TZ, RW, etc.
            $table->decimal('min_amount', 14, 2);
            $table->decimal('max_amount', 14, 2)->nullable();  // null = no upper limit
            $table->decimal('rate', 8, 4);       // percentage e.g. 30.0000
            $table->timestamps();

            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paye_tax_brackets');
        Schema::dropIfExists('deduction_types');
        Schema::dropIfExists('allowance_types');
    }
};
