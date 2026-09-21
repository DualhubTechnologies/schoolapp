<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields a Ugandan school payroll needs:
 *
 *   staff               NIN, gender, employment type, and whether the
 *                       person contributes to NSSF / pays Local Service Tax
 *   payroll_entries     taxable income and LST per payslip
 *   payroll_periods     PAYE, NSSF and LST totals for the URA/NSSF returns,
 *                       and how/when salaries were paid
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('name');
            $table->string('nin', 20)->nullable()->after('tin_number');
            $table->string('employment_type', 20)->default('permanent')->after('department');
            $table->boolean('pays_nssf')->default(true)->after('status');
            $table->boolean('pays_lst')->default(true)->after('pays_nssf');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->decimal('taxable_income', 14, 2)->default(0)->after('gross_pay');
            $table->decimal('lst', 14, 2)->default(0)->after('paye');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->decimal('total_paye', 14, 2)->default(0)->after('total_statutory');
            $table->decimal('total_nssf_employee', 14, 2)->default(0)->after('total_paye');
            $table->decimal('total_lst', 14, 2)->default(0)->after('total_nssf_employee');
            $table->date('payment_date')->nullable()->after('paid_at');
            $table->string('payment_method', 20)->nullable()->after('payment_date');
            $table->string('payment_reference')->nullable()->after('payment_method');
        });

        Schema::table('payroll_entry_items', function (Blueprint $table) {
            // 'lst' joins the statutory lines; widen the enum to a string so
            // new categories never need another migration.
            $table->string('category', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['gender', 'nin', 'employment_type', 'pays_nssf', 'pays_lst']);
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn(['taxable_income', 'lst']);
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['total_paye', 'total_nssf_employee', 'total_lst', 'payment_date', 'payment_method', 'payment_reference']);
        });
    }
};
