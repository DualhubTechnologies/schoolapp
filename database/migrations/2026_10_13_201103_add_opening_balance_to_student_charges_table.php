<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Balances brought over from a school's previous system
 * (App\Services\OpeningBalanceCsvImporter): one charge per learner, and
 * whether it already includes the fees of the term it is carried into.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_charges', function (Blueprint $table) {
            $table->boolean('is_opening_balance')->default(false)->after('transport_route_id');
            $table->boolean('includes_term_fees')->default(false)->after('is_opening_balance');
        });
    }

    public function down(): void
    {
        Schema::table('student_charges', function (Blueprint $table) {
            $table->dropColumn(['is_opening_balance', 'includes_term_fees']);
        });
    }
};
