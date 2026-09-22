<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget & expenses: what the school receives against what it spends.
 *
 *   finance_categories  income and expense heads (School fees, Capitation
 *                       grant, Salaries, Feeding, Utilities...). Two are
 *                       filled automatically: fees (from receipts) and
 *                       payroll (from approved payroll runs)
 *   finance_entries     every other income and every expense, numbered
 *                       per school (INC-000001 / EXP-000001) and voided,
 *                       never deleted
 *   budget_lines        the term budget per category
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                  // income | expense
            $table->string('name');
            $table->string('system_key', 20)->nullable(); // fees | payroll: filled automatically
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'type', 'name']);
        });

        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                  // income | expense
            $table->foreignId('finance_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('voucher_seq');
            $table->string('voucher_no', 20);
            $table->date('entry_date');
            $table->decimal('amount', 14, 2);
            $table->string('party')->nullable();          // paid to / received from
            $table->string('description');
            $table->string('method', 20)->default('cash'); // cash | bank | mobile_money | cheque
            $table->string('reference')->nullable();      // cheque / transaction / invoice no.
            $table->string('attachment')->nullable();     // scanned receipt or invoice
            $table->string('recorded_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('voided_by')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'type', 'voucher_seq']);
            $table->index(['school_id', 'type', 'entry_date']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['term_id', 'finance_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('finance_entries');
        Schema::dropIfExists('finance_categories');
    }
};
