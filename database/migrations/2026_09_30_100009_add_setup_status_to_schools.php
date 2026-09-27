<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a school has answered the first-sign-in setup offer
 * (App\Services\SchoolStarterSetup): 'recommended' when it took the
 * Uganda defaults, 'skipped' when it chose to set up by hand. Schools
 * that existed before the offer are marked 'existing' so they never see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->timestamp('setup_completed_at')->nullable()->after('terms_accepted_ip');
            $table->string('setup_choice', 20)->nullable()->after('setup_completed_at');
        });

        DB::table('schools')->update(['setup_completed_at' => now(), 'setup_choice' => 'existing']);
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['setup_completed_at', 'setup_choice']);
        });
    }
};
