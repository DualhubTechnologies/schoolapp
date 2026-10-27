<?php

namespace App\Services\Sync;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One school's synced records in one database: the online server's or the
 * Windows app's. Both sides use it the same way.
 *
 * A record's id differs on each side, so records travel with a shared id
 * (uuid, kept in sync_rows) and every link to another record (a learner's
 * class, a payment's learner) travels as that record's uuid. Links to
 * user accounts travel as the account's email.
 */
class SyncDatabase
{
    /** @var array<string, array<string, string>> */
    protected static array $foreignKeys = [];

    /** @var array<string, list<string>> */
    protected static array $columns = [];

    /** @var array<string, array<int, string>> table => row id => uuid */
    protected array $uuids = [];

    /** @var array<string, array<string, int>> table => uuid => row id */
    protected array $ids = [];

    public function __construct(public readonly int $schoolId, public readonly ?string $connection = null) {}

    public function db(): Connection
    {
        return DB::connection($this->connection);
    }

    /** The school's records in a synced table. */
    public function query(string $table): Builder
    {
        $via = SyncTables::rules($table)['via'] ?? null;

        if ($via === null) {
            return $this->db()->table($table)->where("{$table}.school_id", $this->schoolId);
        }

        [$parent, $column] = $via;

        return $this->db()->table($table)->whereIn("{$table}.{$column}", fn (Builder $query) => $query->select('id')->from($parent)->where('school_id', $this->schoolId));
    }

    /** @return array<string, mixed>|null */
    public function find(string $table, int $id): ?array
    {
        $row = $this->db()->table($table)->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * A fingerprint of a record's contents, to notice when it changes.
     *
     * @param  array<string, mixed>  $row
     */
    public function fingerprint(array $row): string
    {
        unset($row['id']);
        ksort($row);

        return sha1((string) json_encode(array_map(fn ($value) => $value === null ? null : (string) $value, $row)));
    }

    /**
     * The table's links to other tables (column => table), school_id aside.
     *
     * @return array<string, string>
     */
    public function foreignKeys(string $table): array
    {
        $key = ($this->connection ?? 'default').':'.$table;

        return self::$foreignKeys[$key] ??= collect($this->db()->getSchemaBuilder()->getForeignKeys($table))
            ->filter(fn (array $key): bool => count($key['columns']) === 1 && $key['columns'][0] !== 'school_id')
            ->mapWithKeys(fn (array $key): array => [$key['columns'][0] => (string) $key['foreign_table']])
            ->all();
    }

    /** @return list<string> */
    public function columns(string $table): array
    {
        $key = ($this->connection ?? 'default').':'.$table;

        return self::$columns[$key] ??= $this->db()->getSchemaBuilder()->getColumnListing($table);
    }

    public function uuidOf(string $table, int $id): ?string
    {
        $this->load($table);

        return $this->uuids[$table][$id] ?? null;
    }

    public function idOf(string $table, string $uuid): ?int
    {
        $this->load($table);

        return $this->ids[$table][$uuid] ?? null;
    }

    /** @return array<int, string> row id => uuid */
    public function mapped(string $table): array
    {
        $this->load($table);

        return $this->uuids[$table];
    }

    protected function load(string $table): void
    {
        if (isset($this->uuids[$table])) {
            return;
        }

        $rows = $this->db()->table('sync_rows')
            ->where('school_id', $this->schoolId)
            ->where('table', $table)
            ->where('deleted', false)
            ->get(['row_id', 'uuid']);

        $this->uuids[$table] = $rows->mapWithKeys(fn (object $row): array => [(int) $row->row_id => (string) $row->uuid])->all();
        $this->ids[$table] = array_flip($this->uuids[$table]);
    }

    /**
     * Records which uuid a record has, with any other sync_rows values.
     *
     * @param  array<string, mixed>  $values
     */
    public function map(string $table, int $rowId, string $uuid, array $values = []): void
    {
        $this->load($table);

        $this->db()->table('sync_rows')->updateOrInsert(['uuid' => $uuid], [
            'school_id' => $this->schoolId,
            'table' => $table,
            'row_id' => $rowId,
            'deleted' => false,
            ...$values,
        ]);

        if (isset($this->uuids[$table][$rowId]) && $this->uuids[$table][$rowId] !== $uuid) {
            unset($this->ids[$table][$this->uuids[$table][$rowId]]);
        }

        $this->uuids[$table][$rowId] = $uuid;
        $this->ids[$table][$uuid] = $rowId;
    }

    /** Forgets a uuid (its record is gone on both sides). */
    public function forget(string $table, string $uuid): void
    {
        $this->load($table);
        $this->db()->table('sync_rows')->where('uuid', $uuid)->delete();

        if (isset($this->ids[$table][$uuid])) {
            unset($this->uuids[$table][$this->ids[$table][$uuid]], $this->ids[$table][$uuid]);
        }
    }

    /** Gives a record a new uuid (a record first added on this side). */
    public function assign(string $table, int $rowId, string $origin = 'here'): string
    {
        $uuid = (string) Str::uuid();
        $this->map($table, $rowId, $uuid, ['hash' => null, 'origin' => $origin]);

        return $uuid;
    }

    /** Clears the cached uuids (after sync_rows changed underneath). */
    public function refresh(): void
    {
        $this->uuids = [];
        $this->ids = [];
    }

    /**
     * A record as it travels: links as uuids, user accounts as emails. A
     * link to a record with no uuid yet travels as ['ref' => null].
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function encode(string $table, array $row): array
    {
        $data = Arr::except($row, ['id', 'school_id']);

        foreach ($this->foreignKeys($table) as $column => $foreign) {
            if (! isset($data[$column])) {
                continue;
            }

            $data[$column] = match (true) {
                $foreign === 'users' => ['user' => $this->db()->table('users')->where('id', $data[$column])->value('email')],
                in_array($foreign, SyncTables::names(), true) => ['ref' => $this->uuidOf($foreign, (int) $data[$column])],
                default => null,
            };
        }

        foreach (SyncTables::rules($table)['id_lists'] ?? [] as $column => $foreign) {
            if (isset($data[$column])) {
                $ids = (array) json_decode((string) $data[$column], true);
                $data[$column] = ['refs' => array_values(array_filter(array_map(fn ($id): ?string => $this->uuidOf($foreign, (int) $id), $ids)))];
            }
        }

        return $data;
    }

    /**
     * The tables and uuids an encoded record links to that have no uuid
     * yet on this side, e.g. a payment for a learner still under review.
     *
     * @param  array<string, mixed>  $data
     */
    public static function hasMissingLink(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value) && array_key_exists('ref', $value) && $value['ref'] === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * An incoming record turned into this side's row: uuids back to ids.
     * Returns the row, or the first link not here yet ("students <uuid>").
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    public function decode(string $table, array $data): array
    {
        foreach ($this->foreignKeys($table) as $column => $foreign) {
            if (! array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];

            if (! is_array($value)) {
                $data[$column] = null;

                continue;
            }

            if (array_key_exists('user', $value)) {
                $data[$column] = $value['user'] ? $this->db()->table('users')->where('email', $value['user'])->value('id') : null;

                continue;
            }

            $id = $value['ref'] ? $this->idOf($foreign, (string) $value['ref']) : null;

            if ($id === null) {
                return [[], $foreign.' '.($value['ref'] ?? '?')];
            }

            $data[$column] = $id;
        }

        foreach (SyncTables::rules($table)['id_lists'] ?? [] as $column => $foreign) {
            if (is_array($data[$column] ?? null)) {
                $ids = array_map(fn ($uuid): ?int => $this->idOf($foreign, (string) $uuid), (array) ($data[$column]['refs'] ?? []));
                $data[$column] = json_encode(array_values(array_filter($ids)));
            }
        }

        $row = array_intersect_key($data, array_flip($this->columns($table)));
        unset($row['id']);

        if (! isset(SyncTables::rules($table)['via'])) {
            $row['school_id'] = $this->schoolId;
        }

        return [$row, null];
    }

    /**
     * Saves an incoming record (new or changed) and returns its id here.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mapValues  extra sync_rows values
     *
     * @throws SyncWaiting when it links to a record not here yet
     * @throws UniqueConstraintViolationException when it clashes with another record
     */
    public function save(string $table, string $uuid, array $data, array $mapValues = []): int
    {
        [$row, $waiting] = $this->decode($table, $data);

        if ($waiting !== null) {
            throw new SyncWaiting($waiting);
        }

        $id = $this->idOf($table, $uuid);
        $numbers = SyncTables::rules($table)['numbers'] ?? null;

        if ($id !== null && $this->db()->table($table)->where('id', $id)->exists()) {
            // A running number given here is never changed afterwards.
            if ($numbers !== null) {
                unset($row[$numbers['column']]);
            }

            $this->db()->table($table)->where('id', $id)->update($row);
        } else {
            $id = $this->insert($table, $row);
        }

        $this->map($table, $id, $uuid, ['hash' => $this->fingerprint((array) $this->find($table, $id)), ...$mapValues]);

        return $id;
    }

    /**
     * Updates a record here with incoming details, keeping its id.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws SyncWaiting
     */
    public function overwrite(string $table, int $id, array $data): void
    {
        [$row, $waiting] = $this->decode($table, $data);

        if ($waiting !== null) {
            throw new SyncWaiting($waiting);
        }

        $numbers = SyncTables::rules($table)['numbers'] ?? null;

        if ($numbers !== null) {
            unset($row[$numbers['column']]);
        }

        $this->db()->table($table)->where('id', $id)->update($row);
    }

    /** @param  array<string, mixed>  $row */
    protected function insert(string $table, array $row): int
    {
        try {
            return (int) $this->db()->table($table)->insertGetId($row);
        } catch (UniqueConstraintViolationException $e) {
            $numbers = SyncTables::rules($table)['numbers'] ?? null;

            if ($numbers === null) {
                throw $e;
            }

            $query = $this->query($table);

            foreach ($numbers['group'] as $column) {
                $query->where($column, $row[$column] ?? null);
            }

            $row[$numbers['column']] = ((int) $query->max($numbers['column'])) + 1;

            return (int) $this->db()->table($table)->insertGetId($row);
        }
    }

    /**
     * Whether a record must stay even when deleted on the other side: a
     * learner with fees records (receipts, voided ones too, or bills).
     */
    public function mustKeep(string $table, int $id): bool
    {
        return $table === 'students'
            && ($this->db()->table('student_payments')->where('student_id', $id)->exists()
                || $this->db()->table('student_charges')->where('student_id', $id)->exists());
    }

    /** Deletes the record with this uuid here, if there is one. */
    public function delete(string $table, string $uuid): void
    {
        $id = $this->idOf($table, $uuid);

        if ($id !== null) {
            $this->db()->table($table)->where('id', $id)->delete();
        }

        $this->forget($table, $uuid);
    }
}
