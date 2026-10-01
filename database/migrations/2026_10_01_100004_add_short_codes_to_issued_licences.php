<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short licence codes (FGDH-FWFH-2342-WETR) for the Windows app. The
 * school types the code; the app swaps it once, online, for the signed
 * licence (LicenceActivation). A code may be issued for a named school,
 * or left open for whichever school enters it first; either way it then
 * belongs to that one school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_licences', function (Blueprint $table) {
            $table->string('short_code', 19)->nullable()->unique()->after('licence_no');
            $table->string('school_name', 150)->nullable()->change();
            $table->string('school_code', 20)->nullable()->change();
            $table->text('key')->nullable()->change();
            $table->timestamp('activated_at')->nullable()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('issued_licences', function (Blueprint $table) {
            $table->dropUnique(['short_code']);
            $table->dropColumn(['short_code', 'activated_at']);
        });
    }
};
