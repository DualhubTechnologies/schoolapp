<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_payments', function (Blueprint $table) {
            // Nullable: a payment need not belong to a term. It reduces the
            // balance either way -- the term is only for reporting.
            $table->foreignId('term_id')
                ->nullable()
                ->after('student_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::table('student_payments', function (Blueprint $table) {
            $table->dropForeign(['term_id']);
            $table->dropColumn('term_id');
        });
    }
};
