<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P.1–P.3 report cards get an aggregate and division like the rest of
 * primary: Literacy I, Literacy II, Numeracy and Oral English count as
 * the four core subjects, as schools grade lower primary.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subjects')
            ->where('curriculum', 'primary')
            ->whereIn('name', ['Literacy I', 'Literacy II', 'Numeracy', 'Oral English'])
            ->update(['category' => 'core', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('subjects')
            ->where('curriculum', 'primary')
            ->whereIn('name', ['Literacy I', 'Literacy II', 'Numeracy', 'Oral English'])
            ->update(['category' => 'standard', 'updated_at' => now()]);
    }
};
