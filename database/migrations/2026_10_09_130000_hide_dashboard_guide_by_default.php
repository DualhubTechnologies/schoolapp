<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The step-by-step guide on the dashboard is now closed unless a user
 * opens it, so what is stored is when they opened it, not when they
 * hid it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tips_shown_at')->nullable();
            $table->dropColumn('tips_hidden_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tips_hidden_at')->nullable();
            $table->dropColumn('tips_shown_at');
        });
    }
};
