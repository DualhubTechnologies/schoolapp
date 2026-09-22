<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *   users.modules    the parts of the system a user may open, chosen by the
 *                    school administrator (null = the defaults for the
 *                    user's role)
 *   staff.initials   how a teacher signs, e.g. "B.K." -- shown against
 *                    their subjects on report cards
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('school_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('initials', 10)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('modules');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('initials');
        });
    }
};
