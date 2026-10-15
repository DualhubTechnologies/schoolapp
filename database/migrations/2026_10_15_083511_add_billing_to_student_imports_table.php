<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_imports', function (Blueprint $table) {
            // Learners imported after the term was billed are billed straight
            // away; what that came to is shown when the import finishes.
            $table->unsignedInteger('billed_students')->default(0)->after('duplicate_rows');
            $table->decimal('billed_amount', 14, 2)->default(0)->after('billed_students');
        });
    }

    public function down(): void
    {
        Schema::table('student_imports', function (Blueprint $table) {
            $table->dropColumn(['billed_students', 'billed_amount']);
        });
    }
};
