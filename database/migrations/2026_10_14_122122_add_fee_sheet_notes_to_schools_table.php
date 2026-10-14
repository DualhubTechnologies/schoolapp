<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a school prints under its fees structure: the requirements a
 * learner brings (reams, toilet paper, brooms...) and its rules on paying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->text('fee_sheet_notes')->nullable()->after('fee_payment_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('fee_sheet_notes');
        });
    }
};
