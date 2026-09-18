<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One flat table of charges, replacing invoices + invoice_items.
        //
        // The invoice is printed on demand from the ledger, so there was
        // never an invoice document to store -- the old invoices table was
        // only grouping charges and carrying a number nothing referenced.
        Schema::create('student_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();

            // Null for one-off charges typed in by the bursar.
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->decimal('amount', 14, 2);

            // Discount resolved to a figure at the moment of charging, so
            // the statement can show the reduction plainly and changing a
            // discount later cannot silently rewrite history.
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->string('discount_reason')->nullable();

            $table->date('charged_on');
            $table->string('currency', 8)->default('UGX');
            $table->string('created_by')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['student_id', 'term_id']);
            $table->index(['fee_structure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_charges');
    }
};
