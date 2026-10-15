<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The AI Insights pop-up shows once a day at most (App\Filament\App\Widgets\Insights).
            $table->date('insights_seen_on')->nullable()->after('tips_shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('insights_seen_on');
        });
    }
};
