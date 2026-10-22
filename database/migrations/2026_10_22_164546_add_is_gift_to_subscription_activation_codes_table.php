<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gift vouchers: codes SchoolHub gives away (nothing paid). One starts
 * after the school's free term instead of cutting it short.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->dropColumn('is_gift');
        });
    }
};
