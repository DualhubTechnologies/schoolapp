<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('nssf_employer_number')->nullable()->after('email');
            $table->string('tin_number')->nullable()->after('nssf_employer_number');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['nssf_employer_number', 'tin_number']);
        });
    }
};