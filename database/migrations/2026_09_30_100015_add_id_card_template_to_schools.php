<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which student ID card design the school prints: 'classic' (landscape) or
 * 'portrait'. Chosen once on the ID Cards page and remembered from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('id_card_template', 20)->default('classic')->after('hm_signature');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('id_card_template');
        });
    }
};
