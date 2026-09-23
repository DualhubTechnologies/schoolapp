<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How parents should pay fees to the school itself -- separate from
 * config/subscriptions.php, which is how the school pays SchoolHub.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('fee_payment_bank')->nullable()->after('website');
            $table->string('fee_payment_mobile_money')->nullable()->after('fee_payment_bank');
            $table->text('fee_payment_instructions')->nullable()->after('fee_payment_mobile_money');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['fee_payment_bank', 'fee_payment_mobile_money', 'fee_payment_instructions']);
        });
    }
};
