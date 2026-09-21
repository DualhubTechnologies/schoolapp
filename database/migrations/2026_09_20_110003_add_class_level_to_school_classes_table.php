<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            // Nullable so a class can exist before levels are set up.
            // Such a class simply prints without one.
            $table->foreignId('class_level_id')
                ->nullable()
                ->after('school_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['school_id', 'class_level_id']);
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['class_level_id']);
            $table->dropColumn('class_level_id');
        });
    }
};
