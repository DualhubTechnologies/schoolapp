<?php

namespace App\Services\Sync;

use App\Models\School;
use App\Support\Edition;
use App\Support\Licensing\DesktopLicence;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * The Windows app's side of syncing with the school's online account.
 *
 * A sync (run) has three steps:
 *   1. fetch what changed online into sync_inbox;
 *   2. save it here: a record changed on both sides keeps the latest
 *      edit; a record added on both sides with the same name (a class, a
 *      subject, a mark) is joined up; a possible duplicate learner or
 *      staff member, a payment or expense like one recorded here, and a
 *      finance record deleted online go to the review list (sync_reviews)
 *      and wait there, with anything that depends on them;
 *   3. send what changed here since the last sync, found by comparing
 *      each record with its fingerprint from then (sync_rows), checking
 *      new learners, staff and payments against records from online the
 *      same way. Deleting a payment or expense here is also confirmed on
 *      the review list before it is deleted online.
 */
class SyncClient
{
    public const TOKEN = 'sync.token';

    public const SCHOOL = 'sync.school';

    public const CURSOR = 'sync.cursor';

    public const LAST = 'sync.last';

    public const RESULT = 'sync.result';

    /** Changes sent per request. */
    protected const BATCH = 300;

    protected SyncTransport $transport;

    public function __construct(?SyncTransport $transport = null, protected ?string $connection = null)
    {
        $this->transport = $transport ?? new HttpSyncTransport;
    }

    public function database(): SyncDatabase
    {
        return new SyncDatabase((int) DB::connection($this->connection)->table('schools')->orderBy('id')->value('id'), $this->connection);
    }

    /**
     * "W" in the receipt and voucher numbers of a linked Windows app
     * (RCT-W000016), so they never match numbers given online.
     */
    public static function numberMark(): string
    {
        return Edition::isDesktop() && DB::table('app_state')->where('key', self::TOKEN)->exists() ? 'W' : '';
    }

    public function isLinked(): bool
    {
        return $this->state(self::TOKEN) !== null;
    }

    /** The online school this computer is linked to. */
    public function linkedSchool(): ?string
    {
        return $this->state(self::SCHOOL);
    }

    /** Links this computer with a code made online. */
    public function link(string $code): string
    {
        $answer = $this->transport->post('link', ['code' => $code, 'name' => gethostname() ?: 'Windows computer']);

        if (blank($answer['token'] ?? null)) {
            throw new RuntimeException('Online SchoolHub did not accept the link code.');
        }

        $this->put(self::TOKEN, Crypt::encryptString((string) $answer['token']));
        $this->put(self::SCHOOL, (string) ($answer['school'] ?? ''));
        $this->put(self::CURSOR, '0');

        return (string) ($answer['school'] ?? '');
    }

    /** Unlinks this computer. Its records stay; the sync records go. */
    public function unlink(): void
    {
        $db = DB::connection($this->connection);
        $db->table('app_state')->where('key', 'like', 'sync.%')->delete();
        $db->table('sync_rows')->delete();
        $db->table('sync_inbox')->delete();
        $db->table('sync_reviews')->where('status', 'open')->delete();
    }

    /**
     * Syncs now. Throws ConnectionException when offline.
     *
     * @return array{received: int, sent: int, review: int, waiting: int}
     */
    public function run(): array
    {
        if (! $this->isLinked()) {
            throw new RuntimeException('This computer is not linked to an online account yet.');
        }

        return Cache::lock('sync-run', 600)->block(5, function (): array {
            $db = $this->database();
            $received = $this->fetch($db);
            $this->applyInbox($db);
            $sent = $this->send($db);

            $result = [
                'received' => $received,
                'sent' => $sent,
                'review' => $db->db()->table('sync_reviews')->where('status', 'open')->count(),
                'waiting' => $db->db()->table('sync_inbox')->count(),
            ];

            $this->put(self::LAST, now()->toIso8601String());
            $this->put(self::RESULT, (string) json_encode($result));

            return $result;
        });
    }

    /** Step 1: what changed online, into the inbox. */
    protected function fetch(SyncDatabase $db): int
    {
        $cursor = (int) $this->state(self::CURSOR, '0');
        $received = 0;
        $scan = true;

        // Finance records deleted here: their online copy, to show on the review list.
        $fetch = $db->db()->table('sync_reviews')->where('status', 'open')->where('kind', 'deleted_here')->whereNull('remote_data')->pluck('remote_uuid')->filter()->values()->all();

        $school = School::on($this->connection)->find($db->schoolId);
        $computer = ['name' => (string) $school?->name, 'code' => (string) $school?->unique_code];

        do {
            $page = $this->transport->post('pull', ['cursor' => $cursor, 'scan' => $scan, 'fetch' => $fetch, 'school' => $computer], $this->token());

            // Paid online: the licence for this computer comes with the first page.
            if ($scan && $school !== null && is_string($page['licence'] ?? null)) {
                DesktopLicence::keepFromOnline($school, $page['licence']);
            }

            $scan = false;
            $fetch = [];

            foreach ((array) ($page['changes'] ?? []) as $change) {
                $this->receive($db, (array) $change);
                $received++;
            }

            $cursor = (int) ($page['cursor'] ?? $cursor);
            $this->put(self::CURSOR, (string) $cursor);
        } while (! empty($page['more']));

        return $received;
    }

    /** @param  array<string, mixed>  $change */
    protected function receive(SyncDatabase $db, array $change): void
    {
        $table = (string) ($change['t'] ?? '');
        $uuid = (string) ($change['u'] ?? '');

        if (! in_array($table, SyncTables::names(), true) || $uuid === '') {
            return;
        }

        $data = $change['data'] ?? null;

        // The online copy of a finance record deleted here, asked for to show on the review list.
        $review = $db->db()->table('sync_reviews')->where('status', 'open')->where('kind', 'deleted_here')->where('remote_uuid', $uuid)->first();

        if ($review !== null) {
            if ($data !== null) {
                $db->db()->table('sync_reviews')->where('id', $review->id)->update(['remote_data' => json_encode($data), 'updated_at' => now()]);
            }

            return;
        }

        $db->db()->table('sync_inbox')->updateOrInsert(['uuid' => $uuid], [
            'table' => $table,
            'deleted' => ! empty($change['d']),
            'data' => $data !== null ? json_encode($data) : null,
            'changed_at' => isset($change['at']) ? now()->parse((string) $change['at']) : now(),
            'waiting' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    /** Step 2: saves what came from online. */
    public function applyInbox(SyncDatabase $db): void
    {
        $items = $db->db()->table('sync_inbox')->orderBy('id')->get();

        $upserts = $items->where('deleted', false)->sortBy(fn (stdClass $item): array => [SyncTables::position($item->table), $item->id]);
        $deletes = $items->where('deleted', true)->sortByDesc(fn (stdClass $item): int => SyncTables::position($item->table));

        foreach ($upserts as $item) {
            $this->applyUpsert($db, $item);
        }

        foreach ($deletes as $item) {
            $this->applyDelete($db, $item);
        }
    }

    protected function applyUpsert(SyncDatabase $db, stdClass $item): void
    {
        $table = (string) $item->table;
        $uuid = (string) $item->uuid;
        $data = (array) json_decode((string) $item->data, true);

        if ($this->hasOpenReview($db, remoteUuid: $uuid)) {
            $this->wait($db, $item, 'review');

            return;
        }

        try {
            $localId = $db->idOf($table, $uuid);
            $local = $localId !== null ? $db->find($table, $localId) : null;

            if ($local !== null) {
                if ($this->changedHere($db, $uuid, $local) && ! SyncServer::isNewer($item->changed_at, $local['updated_at'] ?? null)) {
                    $this->done($db, $item); // the edit here is newer: it goes online in step 3

                    return;
                }

                $db->save($table, $uuid, $data);
                $this->done($db, $item);

                return;
            }

            if ($localId === null) {
                [$row, $waiting] = $db->decode($table, $data);

                if ($waiting !== null) {
                    throw new SyncWaiting($waiting);
                }

                $same = $this->sameRecordHere($db, $table, $row);

                if ($same !== null) {
                    // Added on both sides: one record from now on, with the latest details.
                    $db->map($table, $same, $uuid, ['hash' => null, 'origin' => 'there']);
                    $here = (array) $db->find($table, $same);

                    if (SyncServer::isNewer($item->changed_at, $here['updated_at'] ?? null)) {
                        $db->save($table, $uuid, $data);
                    }

                    $this->done($db, $item);

                    return;
                }

                $duplicate = DuplicateFinder::find($db, $table, $row, from: 'here');

                if ($duplicate !== null) {
                    $this->review($db, $duplicate['kind'], $table, $duplicate['id'], $uuid, $data);
                    $this->wait($db, $item, 'review');

                    return;
                }
            }

            $db->save($table, $uuid, $data, ['origin' => 'there']);
            $this->done($db, $item);
        } catch (SyncWaiting $e) {
            $this->wait($db, $item, $e->for);
        } catch (UniqueConstraintViolationException) {
            $this->review($db, 'clash', $table, null, $uuid, $data);
            $this->wait($db, $item, 'review');
        }
    }

    protected function applyDelete(SyncDatabase $db, stdClass $item): void
    {
        $table = (string) $item->table;
        $uuid = (string) $item->uuid;
        $localId = $db->idOf($table, $uuid);
        $local = $localId !== null ? $db->find($table, $localId) : null;

        if ($local === null) {
            $db->forget($table, $uuid);
            $this->done($db, $item);

            return;
        }

        if ($this->changedHere($db, $uuid, $local)) {
            // Edited here after it was deleted online: the edit wins and goes back online.
            $this->done($db, $item);

            return;
        }

        if (SyncTables::isFinance($table)) {
            if (! $this->hasOpenReview($db, remoteUuid: $uuid)) {
                $this->review($db, 'deleted_there', $table, $localId, $uuid, null);
            }
            $this->wait($db, $item, 'review');

            return;
        }

        try {
            $db->delete($table, $uuid);
            $this->done($db, $item);
        } catch (QueryException) {
            $this->wait($db, $item, 'records that still point to it');
        }
    }

    /** Step 3: sends what changed here. Returns how many were saved online. */
    protected function send(SyncDatabase $db): int
    {
        $upserts = [];
        $deletes = [];
        $hashes = [];

        foreach (SyncTables::names() as $table) {
            $entries = $db->db()->table('sync_rows')->where('school_id', $db->schoolId)->where('table', $table)->get(['uuid', 'row_id', 'hash', 'deleted', 'delete_approved'])->keyBy('uuid');
            $mapped = $db->mapped($table);
            $seen = [];

            foreach ($db->query($table)->orderBy("{$table}.id")->select("{$table}.*")->cursor() as $row) {
                $row = (array) $row;
                $id = (int) $row['id'];
                $seen[$id] = true;
                $uuid = $mapped[$id] ?? null;
                $hash = $db->fingerprint($row);

                if ($uuid === null) {
                    if ($this->hasOpenReview($db, table: $table, localId: $id)) {
                        continue;
                    }

                    if (isset(SyncTables::rules($table)['review'])) {
                        $duplicate = DuplicateFinder::find($db, $table, $row, from: 'there', except: $id);

                        if ($duplicate !== null) {
                            $this->review($db, $duplicate['kind'], $table, $id, $db->uuidOf($table, $duplicate['id']), null);

                            continue;
                        }
                    }

                    $uuid = $db->assign($table, $id);
                } elseif ($entries[$uuid]->hash === $hash) {
                    continue;
                }

                $data = $db->encode($table, $row);

                if (SyncDatabase::hasMissingLink($data)) {
                    continue; // points to a record still under review
                }

                $upserts[] = ['t' => $table, 'u' => $uuid, 'at' => $row['updated_at'] ?? null, 'data' => $data];
                $hashes[$uuid] = $hash;
            }

            foreach ($entries as $uuid => $entry) {
                $gone = $entry->deleted || ! isset($seen[(int) $entry->row_id]);

                if (! $gone) {
                    continue;
                }

                if (SyncTables::isFinance($table) && ! $entry->delete_approved) {
                    if (! $this->hasOpenReview($db, remoteUuid: (string) $uuid)) {
                        $this->review($db, 'deleted_here', $table, (int) $entry->row_id, (string) $uuid, null);
                    }

                    continue;
                }

                $deletes[] = ['t' => $table, 'u' => (string) $uuid, 'd' => true];
            }
        }

        $sent = 0;
        $cursor = (int) $this->state(self::CURSOR, '0');

        foreach (array_chunk([...$upserts, ...array_reverse($deletes)], self::BATCH) as $batch) {
            $results = (array) ($this->transport->post('push', ['cursor' => $cursor, 'changes' => $batch], $this->token())['results'] ?? []);

            foreach ($batch as $change) {
                $result = $results[$change['u']] ?? null;
                $table = $change['t'];

                if ($result === 'ok') {
                    $sent++;

                    if (! empty($change['d'])) {
                        $db->forget($table, $change['u']);
                    } else {
                        $db->db()->table('sync_rows')->where('uuid', $change['u'])->update(['hash' => $hashes[$change['u']]]);
                    }
                } elseif ($result === 'clash') {
                    $this->review($db, 'clash', $table, $db->idOf($table, $change['u']), $change['u'], null);
                }
            }
        }

        return $sent;
    }

    /**
     * A record here with the same name or key (SyncTables `same`) that is
     * not linked to an online record yet.
     *
     * @param  array<string, mixed>  $row
     */
    protected function sameRecordHere(SyncDatabase $db, string $table, array $row): ?int
    {
        $same = SyncTables::rules($table)['same'] ?? null;

        if ($same === null) {
            return null;
        }

        $query = $db->query($table);

        foreach ($same as $column) {
            ($row[$column] ?? null) === null ? $query->whereNull("{$table}.{$column}") : $query->where("{$table}.{$column}", $row[$column]);
        }

        $mapped = $db->mapped($table);

        foreach ($query->orderBy("{$table}.id")->pluck("{$table}.id") as $id) {
            if (! isset($mapped[(int) $id])) {
                return (int) $id;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    protected function changedHere(SyncDatabase $db, string $uuid, array $row): bool
    {
        $base = $db->db()->table('sync_rows')->where('uuid', $uuid)->value('hash');

        return $base === null || $db->fingerprint($row) !== $base;
    }

    protected function hasOpenReview(SyncDatabase $db, ?string $remoteUuid = null, ?string $table = null, ?int $localId = null): bool
    {
        $query = $db->db()->table('sync_reviews')->where('status', 'open');

        if ($remoteUuid !== null) {
            return $query->where('remote_uuid', $remoteUuid)->exists();
        }

        return $query->where('table', $table)->where('local_id', $localId)->exists();
    }

    /** @param  array<string, mixed>|null  $data */
    protected function review(SyncDatabase $db, string $kind, string $table, ?int $localId, ?string $remoteUuid, ?array $data): void
    {
        $open = $db->db()->table('sync_reviews')->where('status', 'open')->where('table', $table)
            ->where(fn ($query) => $remoteUuid !== null ? $query->where('remote_uuid', $remoteUuid) : $query->where('local_id', $localId))
            ->exists();

        if ($open) {
            return;
        }

        $db->db()->table('sync_reviews')->insert([
            'kind' => $kind,
            'table' => $table,
            'local_id' => $localId,
            'remote_uuid' => $remoteUuid,
            'remote_data' => $data !== null ? json_encode($data) : null,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function wait(SyncDatabase $db, stdClass $item, string $for): void
    {
        $db->db()->table('sync_inbox')->where('id', $item->id)->update(['waiting' => mb_substr($for, 0, 250)]);
    }

    protected function done(SyncDatabase $db, stdClass $item): void
    {
        $db->db()->table('sync_inbox')->where('id', $item->id)->delete();
    }

    protected function token(): string
    {
        return Crypt::decryptString((string) $this->state(self::TOKEN));
    }

    public function state(string $key, ?string $default = null): ?string
    {
        $value = DB::connection($this->connection)->table('app_state')->where('key', $key)->value('value');

        return $value !== null ? (string) $value : $default;
    }

    protected function put(string $key, string $value): void
    {
        DB::connection($this->connection)->table('app_state')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }
}
