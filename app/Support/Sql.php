<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * SQL that differs between the server's MySQL and the Windows app's
 * SQLite. Column names passed in are the code's own, never user input.
 */
class Sql
{
    /**
     * "2026-09" from a date column, for grouping by month.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function yearMonth(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
