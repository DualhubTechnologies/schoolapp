<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff salary history — new row per raise, never overwrite
        Schema::create('staff_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_salary', 14, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();  // null = current salary
            $table->string('notes')->nullable();        // e.g. "Annual increment", "Promotion to HOD"
            $table->timestamps();

            $table->index(['staff_id', 'effective_from']);
        });

        // Which allowances each staff member receives
        Schema::create('staff_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['staff_id', 'allowance_type_id']);
        });

        // Which deductions apply to each staff member
        Schema::create('staff_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2)->nullable();       // for fixed deductions
            $table->decimal('rate', 8, 4)->nullable();           // for percentage deductions (overrides type default)
            $table->boolean('is_recurring')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();                // null = ongoing (e.g. NSSF never ends)
            $table->decimal('total_amount', 14, 2)->nullable();  // for loans: total loan amount
            $table->decimal('amount_recovered', 14, 2)->default(0); // for loans: how much recovered so far
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Bank details for payment processing
        Schema::create('staff_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_name');
            $table->string('account_number');
            $table->enum('payment_method', ['bank_transfer', 'mobile_money', 'cash'])->default('bank_transfer');
            $table->string('mobile_money_number')->nullable();   // for mobile money payments
            $table->string('mobile_money_provider')->nullable(); // MTN, Airtel, etc.
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });

        // Salary arrears — back-pay owed
        Schema::create('salary_arrears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reason');             // e.g. "Salary adjustment backdated to July"
            $table->unsignedTinyInteger('month'); // which month is the arrear for
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->unsignedBigInteger('applied_in_period_id')->nullable(); // links to payroll_periods when paid
            $table->timestamps();

            $table->index(['staff_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_arrears');
        Schema::dropIfExists('staff_bank_details');
        Schema::dropIfExists('staff_deductions');
        Schema::dropIfExists('staff_allowances');
        Schema::dropIfExists('staff_salaries');
    }
};
