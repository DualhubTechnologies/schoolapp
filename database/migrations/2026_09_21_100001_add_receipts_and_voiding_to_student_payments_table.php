<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns payments into proper receipts:
 *
 *   receipt_seq / receipt_no  numbered per school, never reused
 *   paid_by / payer_phone     who brought the money (often not the guardian)
 *   voided_*                  a wrong receipt is voided with a reason, never
 *                             deleted, so the receipt book has no gaps
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_payments', function (Blueprint $table) {
            $table->unsignedInteger('receipt_seq')->nullable()->after('student_id');
            $table->string('receipt_no', 30)->nullable()->after('receipt_seq');
            $table->string('paid_by')->nullable()->after('reference');
            $table->string('payer_phone', 30)->nullable()->after('paid_by');
            $table->timestamp('voided_at')->nullable()->after('notes');
            $table->string('voided_by')->nullable()->after('voided_at');
            $table->string('void_reason')->nullable()->after('voided_by');

            $table->unique(['school_id', 'receipt_seq']);
            $table->index(['school_id', 'voided_at']);
        });

        // Number any existing payments in the order they were recorded.
        foreach (DB::table('student_payments')->distinct()->pluck('school_id') as $schoolId) {
            $seq = 0;

            foreach (DB::table('student_payments')->where('school_id', $schoolId)->orderBy('id')->pluck('id') as $id) {
                $seq++;
                DB::table('student_payments')->where('id', $id)->update([
                    'receipt_seq' => $seq,
                    'receipt_no' => sprintf('RCT-%06d', $seq),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('student_payments', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'receipt_seq']);
            $table->dropIndex(['school_id', 'voided_at']);
            $table->dropColumn(['receipt_seq', 'receipt_no', 'paid_by', 'payer_phone', 'voided_at', 'voided_by', 'void_reason']);
        });
    }
};
