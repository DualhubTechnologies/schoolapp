<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes no longer have to belong to a school from the moment they are
 * made -- the platform owner can generate a batch ahead of time (a stock
 * of "vouchers" to keep on their phone) and hand one out the moment a
 * school pays, without touching the admin panel. The code is claimed by
 * whichever school redeems it first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
        });

        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->change();
        });

        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->foreign('school_id')->references('id')->on('schools')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
        });

        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable(false)->change();
        });

        Schema::table('subscription_activation_codes', function (Blueprint $table) {
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });
    }
};
