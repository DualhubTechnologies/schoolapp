<?php

namespace App\Services\Sync;

use Illuminate\Database\Query\Builder;

/**
 * Spots a record added on one side that looks like one added on the other,
 * for the review list (SyncTables `review`):
 *
 *   learner   same name in the same class: a duplicate. A different
 *             learner with the same admission number: a number clash.
 *   staff     same name: a duplicate; same staff number, other name: a clash.
 *   payment   same learner, amount and date, whatever the receipt numbers.
 *   entry     same type (income or expense), amount and date.
 *
 * Only records from opposite sides are compared: `from` says which side
 * the candidates here must have come from ("here": added on this
 * computer, synced or not; "there": came from online).
 */
class DuplicateFinder
{
    /**
     * @param  array<string, mixed>  $row  the incoming record, with ids of this side
     * @return array{kind: string, id: int}|null kind: duplicate, number, payment or entry
     */
    public static function find(SyncDatabase $db, string $table, array $row, string $from, ?int $except = null): ?array
    {
        $rule = SyncTables::rules($table)['review'] ?? null;

        $checks = match ($rule) {
            'learner' => [
                'duplicate' => fn (Builder $q) => $q->where('school_class_id', $row['school_class_id'] ?? null),
                'number' => filled($row['admission_no'] ?? null) ? fn (Builder $q) => $q->where('admission_no', $row['admission_no']) : null,
            ],
            'staff' => [
                'duplicate' => fn (Builder $q) => $q,
                'number' => filled($row['staff_no'] ?? null) ? fn (Builder $q) => $q->where('staff_no', $row['staff_no']) : null,
            ],
            'payment' => [
                'payment' => fn (Builder $q) => $q->where('student_id', $row['student_id'] ?? null)
                    ->where('amount', $row['amount'] ?? null)
                    ->whereDate('paid_on', (string) ($row['paid_on'] ?? ''))
                    ->whereNull('voided_at'),
            ],
            'entry' => [
                'entry' => fn (Builder $q) => $q->where('type', $row['type'] ?? null)
                    ->where('amount', $row['amount'] ?? null)
                    ->whereDate('entry_date', (string) ($row['entry_date'] ?? ''))
                    ->whereNull('voided_at'),
            ],
            default => [],
        };

        $origins = $db->db()->table('sync_rows')
            ->where('school_id', $db->schoolId)
            ->where('table', $table)
            ->where('deleted', false)
            ->pluck('origin', 'row_id');

        foreach (array_filter($checks) as $kind => $check) {
            $query = $db->query($table);

            if ($except !== null) {
                $query->where("{$table}.id", '!=', $except);
            }

            $columns = $kind === 'duplicate' ? ["{$table}.id", "{$table}.name"] : ["{$table}.id"];

            foreach ($check($query)->orderBy("{$table}.id")->get($columns) as $candidate) {
                if ($kind === 'duplicate' && self::normalName($candidate->name) !== self::normalName($row['name'] ?? '')) {
                    continue;
                }

                if (($origins[$candidate->id] ?? 'here') === $from) {
                    return ['kind' => $kind, 'id' => (int) $candidate->id];
                }
            }
        }

        return null;
    }

    /** "  Mugisha  ALLAN " and "mugisha allan" are the same name. */
    protected static function normalName(mixed $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $name)));
    }
}
