<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('motto')->nullable()->after('name');
            $table->string('description')->nullable()->after('motto');
            $table->string('unique_code')->nullable()->unique()->after('description');
            $table->string('website')->nullable()->after('phone');
            $table->string('hm_signature')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['motto', 'description', 'unique_code', 'website', 'hm_signature']);
        });
    }
};
