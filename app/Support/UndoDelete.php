<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "Deleted. Undo": for a short while after deleting a record from a list,
 * it can be put back exactly as it was.
 *
 * The database has no recycle bin, so Undo is only offered when deleting
 * the record removed or unlinked nothing else (no fees, marks, pupils...
 * went with it). Anything bigger keeps its "Are you sure?" question and
 * cannot be undone this way.
 */
class UndoDelete
{
    public const SECONDS = 60;

    /** @var array<string, list<array{0: string, 1: string}>> table => [dependent table, column] */
    protected static array $dependents = [];

    /**
     * Before the delete: the row as it is, if putting it back later would
     * restore everything the delete takes away.
     *
     * @return array{table: string, row: array<string, mixed>, label: string}|null
     */
    public static function snapshot(Model $record, string $label): ?array
    {
        if (DB::connection($record->getConnectionName())->getDriverName() !== 'mysql') {
            return null;
        }

        foreach (static::dependentsOf($record->getTable()) as [$table, $column]) {
            if (DB::table($table)->where($column, $record->getKey())->exists()) {
                return null;
            }
        }

        // Only real columns: list queries add extras such as students_count.
        $columns = Schema::connection($record->getConnectionName())->getColumnListing($record->getTable());
        $row = array_intersect_key($record->getRawOriginal(), array_flip($columns));

        return ['table' => $record->getTable(), 'row' => $row, 'label' => $label];
    }

    /**
     * After the delete: keep the snapshot for SECONDS and return the token
     * that puts it back.
     *
     * @param  array{table: string, row: array<string, mixed>, label: string}  $snapshot
     */
    public static function remember(array $snapshot): string
    {
        $token = Str::random(32);
        Cache::put("undo-delete:{$token}", [...$snapshot, 'user_id' => auth()->id()], self::SECONDS);

        return $token;
    }

    /**
     * Put the record back. Returns its label, or null when the undo has
     * expired or belongs to someone else.
     */
    public static function restore(string $token): ?string
    {
        $snapshot = Cache::pull("undo-delete:{$token}");

        if (! $snapshot || $snapshot['user_id'] !== auth()->id()) {
            return null;
        }

        DB::table($snapshot['table'])->insertOrIgnore($snapshot['row']);

        return $snapshot['label'];
    }

    /**
     * Tables whose rows would be deleted or unlinked along with a row of
     * this table (foreign keys ON DELETE CASCADE / SET NULL).
     *
     * @return list<array{0: string, 1: string}>
     */
    protected static function dependentsOf(string $table): array
    {
        return static::$dependents[$table] ??= array_values(DB::table('information_schema.KEY_COLUMN_USAGE as k')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as r', function ($join) {
                $join->on('r.CONSTRAINT_SCHEMA', '=', 'k.CONSTRAINT_SCHEMA')
                    ->on('r.CONSTRAINT_NAME', '=', 'k.CONSTRAINT_NAME');
            })
            ->whereRaw('k.REFERENCED_TABLE_SCHEMA = DATABASE()')
            ->where('k.REFERENCED_TABLE_NAME', $table)
            ->whereIn('r.DELETE_RULE', ['CASCADE', 'SET NULL'])
            ->get(['k.TABLE_NAME as dependent', 'k.COLUMN_NAME as column'])
            ->map(fn ($row) => [(string) $row->dependent, (string) $row->column])
            ->all());
    }
}
