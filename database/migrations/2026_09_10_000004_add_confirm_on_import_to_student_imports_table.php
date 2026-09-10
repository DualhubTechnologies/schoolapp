<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_imports', function (Blueprint $table) {
            // When true, this batch is the school's existing/continuing students,
            // so imported rows are marked confirmed rather than provisional.
            $table->boolean('confirm_on_import')->default(false)->after('column_map');
        });
    }

    public function down(): void
    {
        Schema::table('student_imports', function (Blueprint $table) {
            $table->dropColumn('confirm_on_import');
        });
    }
};
