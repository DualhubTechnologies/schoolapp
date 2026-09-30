<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff numbers were unique across the whole platform, so two schools
 * could not both have a "ST-001". Each school numbers its own staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['staff_no']);
            $table->unique(['school_id', 'staff_no']);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'staff_no']);
            $table->unique('staff_no');
        });
    }
};
