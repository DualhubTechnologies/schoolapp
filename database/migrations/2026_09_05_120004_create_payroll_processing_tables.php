<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per school per month — the "payroll run"
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');         // 1-12
            $table->unsignedSmallInteger('year');          // 2026, 2027, etc.
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('total_gross', 16, 2)->default(0);
            $table->decimal('total_allowances', 16, 2)->default(0);
            $table->decimal('total_deductions', 16, 2)->default(0);
            $table->decimal('total_statutory', 16, 2)->default(0);  // NSSF + PAYE totals
            $table->decimal('total_net', 16, 2)->default(0);
            $table->decimal('total_employer_nssf', 16, 2)->default(0); // school's NSSF contribution (not deducted from staff)
            $table->unsignedInteger('staff_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'month', 'year']);
        });

        // One row per staff member per payroll period
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_salary', 14, 2);
            $table->decimal('total_allowances', 14, 2)->default(0);
            $table->decimal('gross_pay', 14, 2);               // base + allowances
            $table->decimal('total_deductions', 14, 2)->default(0); // non-statutory deductions
            $table->decimal('nssf_employee', 14, 2)->default(0);    // 5% of gross
            $table->decimal('nssf_employer', 14, 2)->default(0);    // 10% of gross (school's cost, not deducted)
            $table->decimal('paye', 14, 2)->default(0);             // calculated from tax brackets
            $table->decimal('total_statutory', 14, 2)->default(0);  // nssf_employee + paye
            $table->decimal('arrears_amount', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2);                     // gross - all deductions - statutory + arrears
            $table->enum('status', ['included', 'excluded', 'adjusted'])->default('included');
            $table->text('adjustment_notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'staff_id']);
        });

        // Line-item breakdown for each payroll entry (what prints on the payslip)
        Schema::create('payroll_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['allowance', 'deduction', 'statutory', 'arrears']);
            $table->string('name');                    // e.g. "Housing Allowance", "NSSF Employee (5%)", "Staff Loan"
            $table->decimal('amount', 14, 2);
            $table->string('reference_type')->nullable();  // 'allowance_type', 'deduction_type', etc.
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of the source record
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entry_items');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_periods');
    }
};
