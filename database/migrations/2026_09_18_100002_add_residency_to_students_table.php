<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Nullable: a student can exist before anyone decides whether
            // they board. Fees tied to a residency simply will not reach
            // them until it is set.
            $table->foreignId('residency_type_id')
                ->nullable()
                ->after('section_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['school_id', 'residency_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['residency_type_id']);
            $table->dropColumn('residency_type_id');
        });
    }
};
