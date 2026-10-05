<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SchoolPay: parents pay fees to a learner's SchoolPay code, and SchoolHub
 * records each payment itself (App\Services\SchoolPay\SchoolPayPayments).
 *
 *   schools                 the school's SchoolPay code and API password
 *                           (stored encrypted), the secret part of the web
 *                           hook address SchoolPay posts payments to, and
 *                           how the last nightly check went
 *   schoolpay_transactions  every payment SchoolPay reported, once (by its
 *                           receipt number), with the receipt it became --
 *                           or "needs a learner" until the bursar matches it
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('schoolpay_enabled')->default(false);
            $table->string('schoolpay_school_code', 20)->nullable();
            $table->text('schoolpay_api_password')->nullable();
            $table->string('schoolpay_webhook_token', 64)->nullable()->unique();
            $table->timestamp('schoolpay_synced_at')->nullable();
            $table->string('schoolpay_sync_error')->nullable();
        });

        Schema::create('schoolpay_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 40);
            $table->string('type', 20);                  // SCHOOL_FEES | OTHER_FEES
            $table->string('status', 20);                // recorded | unmatched | other_fees
            $table->string('source', 10);                // webhook | sync
            $table->decimal('amount', 14, 2);
            $table->dateTime('paid_at')->nullable();
            $table->string('student_payment_code', 40)->nullable();
            $table->string('student_registration_number', 60)->nullable();
            $table->string('student_name')->nullable();
            $table->string('channel')->nullable();       // MTN MobileMoney, Airtel Money, bank...
            $table->string('channel_transaction_id', 100)->nullable();
            $table->string('fee_description')->nullable(); // other fees: UNIFORM FEES...
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'receipt_number']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schoolpay_transactions');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['schoolpay_webhook_token']);
            $table->dropColumn([
                'schoolpay_enabled',
                'schoolpay_school_code',
                'schoolpay_api_password',
                'schoolpay_webhook_token',
                'schoolpay_synced_at',
                'schoolpay_sync_error',
            ]);
        });
    }
};
