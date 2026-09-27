<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The parent page (App\Http\Controllers\ParentPageController):
 *
 *   students.parent_token          the private part of the short link sent
 *                                  to a learner's parent, /p/{token}
 *   terms.report_cards_released_at when the school let parents see this
 *                                  term's report cards on that page
 *   schools.parent_sms_language    'en' or 'lg' (Luganda): the language of
 *                                  receipts and reminders texted to parents
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('parent_token', 32)->nullable()->unique();
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->timestamp('report_cards_released_at')->nullable();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->string('parent_sms_language', 5)->default('en');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['parent_token']);
            $table->dropColumn('parent_token');
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('report_cards_released_at');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('parent_sms_language');
        });
    }
};
