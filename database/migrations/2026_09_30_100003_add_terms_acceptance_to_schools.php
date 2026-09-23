<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which version of the terms and conditions a school accepted, when, and
 * who ticked the box. Filled in when a school registers itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('terms_version', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_accepted_by')->nullable();   // name <email> of the person registering
            $table->string('terms_accepted_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['terms_version', 'terms_accepted_at', 'terms_accepted_by', 'terms_accepted_ip']);
        });
    }
};
