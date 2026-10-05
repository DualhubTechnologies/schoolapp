<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank reconciliation: does what the bank says went in and out of the
 * school's account agree with what SchoolHub recorded?
 *
 *   bank_accounts         the school's accounts (Centenary school fees
 *                         account...), with the balance they started from
 *   bank_statement_lines  each line of an imported bank statement, once
 *                         (by its fingerprint), and what it was matched to:
 *                         a fee receipt, an income or expense voucher --
 *                         or marked explained (salaries, transfers...)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('bank', 60)->default('Centenary Bank');
            $table->string('account_number', 40)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('line_date');
            $table->string('description', 500);
            $table->string('reference', 100)->nullable();
            $table->decimal('money_in', 14, 2)->default(0);
            $table->decimal('money_out', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('fingerprint', 64);
            $table->string('status', 12)->default('unmatched'); // unmatched | matched | explained
            $table->nullableMorphs('matchable');
            $table->string('note')->nullable();
            $table->string('matched_by')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'fingerprint']);
            $table->index(['bank_account_id', 'status', 'line_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_accounts');
    }
};
