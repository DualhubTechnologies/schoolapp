<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Windows app licences and sync links tied to one computer
 * (App\Support\Licensing\Machine): the computer a short licence code was
 * first entered on, and the computer a sync link belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_licences', function (Blueprint $table) {
            $table->string('machine', 40)->nullable()->after('activated_at');
        });

        Schema::table('sync_devices', function (Blueprint $table) {
            $table->string('machine', 40)->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('sync_devices', function (Blueprint $table) {
            $table->dropColumn('machine');
        });

        Schema::table('issued_licences', function (Blueprint $table) {
            $table->dropColumn('machine');
        });
    }
};
