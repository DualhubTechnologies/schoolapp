<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learner's SchoolPay payment code, for schools whose parents pay fees
 * through SchoolPay. Shown to parents wherever SchoolHub says how to pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('schoolpay_code', 30)->nullable()->after('lin');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('schoolpay_code');
        });
    }
};
