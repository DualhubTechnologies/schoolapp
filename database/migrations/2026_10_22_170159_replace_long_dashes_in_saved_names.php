<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SchoolHub now writes "-" where it used long dashes. Names it saved itself
 * (grading scales, and assessments named "CA 1 - Mathematics") follow.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['grading_scales', 'assessments'] as $table) {
            DB::table($table)
                ->where(fn ($query) => $query->where('name', 'like', '%—%')->orWhere('name', 'like', '%–%'))
                ->orderBy('id')
                ->each(function (object $row) use ($table): void {
                    DB::table($table)->where('id', $row->id)->update(['name' => str_replace(['—', '–'], '-', $row->name)]);
                });
        }
    }

    public function down(): void
    {
        // The old dashes are not put back.
    }
};
