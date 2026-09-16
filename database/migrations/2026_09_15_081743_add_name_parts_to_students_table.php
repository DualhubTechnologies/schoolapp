<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Backfill existing rows: split existing `name` into parts.
        foreach (\DB::table('students')->select('id', 'name')->get() as $row) {
            $parts = preg_split('/\s+/', trim((string) $row->name), 2);
            \DB::table('students')->where('id', $row->id)->update([
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};