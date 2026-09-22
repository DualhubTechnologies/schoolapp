<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grades can be words, not just codes -- nursery reports use "Very good",
 * "Needs support" -- so the column is widened from 10 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_bands', function (Blueprint $table) {
            $table->string('grade', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('grading_bands', function (Blueprint $table) {
            $table->string('grade', 10)->change();
        });
    }
};
