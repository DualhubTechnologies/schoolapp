<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // A school is one type or the other, chosen once at signup.
            // Not configuration -- identity. Report cards, grading and
            // class levels all branch on it, so it belongs here where
            // everything can read it rather than being inferred from
            // whatever classes happen to exist.
            $table->string('school_type')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('school_type');
        });
    }
};
