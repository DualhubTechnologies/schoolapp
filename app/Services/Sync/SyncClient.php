<?php

namespace App\Services\Sync;

use App\Models\School;
use App\Models\User;
use App\Support\Edition;
use App\Support\Licensing\DesktopLicence;
use App\Support\Licensing\Machine;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
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

    /** "Sync now" or a new link: sync at once, in the background. */
    public const WANTED = 'sync.wanted';

    /** A sync running in the background since then. */
    public const RUNNING = 'sync.running';

    /** Why the last sync failed (not for want of internet). */
    public const ERROR = 'sync.error';

    /** Since when online SchoolHub could not be reached. */
    public const OFFLINE = 'sync.offline';

    /** The online school this computer's sync history (sync_rows) belongs to: kept when unlinked. */
    public const HISTORY = 'history.school';

    /** Set up from online, first sync not finished yet (FirstSyncBanner). */
    public const FIRST = 'sync.first';

    /** Changes sent per request. */
    protected const BATCH = 300;

    /** Records saved per transaction. */
    protected const APPLY_BATCH = 100;

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
        $answer = $this->transport->post('link', ['code' => $code, 'name' => gethostname() ?: 'Windows computer', 'machine' => Machine::id()]);

        if (blank($answer['token'] ?? null)) {
            throw new RuntimeException('Online SchoolHub did not accept the link code.');
        }

        $this->linkedTo((string) ($answer['school'] ?? ''));
        $this->put(self::TOKEN, Crypt::encryptString((string) $answer['token']));
        $this->put(self::SCHOOL, (string) ($answer['school'] ?? ''));
        $this->put(self::CURSOR, '0');

        return (string) ($answer['school'] ?? '');
    }

    /**
     * A new Windows app joining its school's online account on first run
     * (DesktopSetupController::connect): links it and returns the school's
     * details and the administrator's name and email.
     *
     * @return array{school: array<string, mixed>, user: array{name: string, email: string}, accounts: list<array<string, mixed>>, licence: string|null}
     */
    public function connect(string $code, string $email, string $password): array
    {
        $answer = $this->transport->post('connect', ['code' => $code, 'email' => $email, 'password' => $password, 'name' => gethostname() ?: 'Windows computer', 'machine' => Machine::id()]);

        if (blank($answer['token'] ?? null) || ! is_array($answer['school'] ?? null)) {
            throw new RuntimeException('Online SchoolHub did not accept these details.');
        }

        $this->linkedTo((string) ($answer['school']['name'] ?? ''));
        $this->put(self::TOKEN, Crypt::encryptString((string) $answer['token']));
        $this->put(self::SCHOOL, (string) ($answer['school']['name'] ?? ''));
        $this->put(self::CURSOR, '0');
        $this->put(self::FIRST, now()->toIso8601String());

        return [
            'school' => $answer['school'],
            'user' => ['name' => (string) ($answer['user']['name'] ?? 'Administrator'), 'email' => (string) ($answer['user']['email'] ?? $email)],
            'accounts' => array_values((array) ($answer['accounts'] ?? [])),
            'licence' => is_string($answer['licence'] ?? null) ? $answer['licence'] : null,
        ];
    }

    /**
     * The school's staff accounts from online: each staff member signs in
     * here with their online email and password, with the same roles.
     * Accounts made only on this computer are left as they are.
     *
     * @param  array<int, mixed>  $accounts
     */
    public function keepAccounts(array $accounts): void
    {
        $schoolId = (int) DB::connection($this->connection)->table('schools')->orderBy('id')->value('id');
        $roles = Role::on($this->connection)->pluck('name')->all();

        foreach ($accounts as $account) {
            $email = trim((string) ($account['email'] ?? ''));

            if ($email === '' || blank($account['password'] ?? null)) {
                continue;
            }

            $user = User::on($this->connection)->where('email', $email)->first() ?? new User;

            if ($user->exists && (int) $user->school_id !== $schoolId) {
                continue;
            }

            $user->setConnection($this->connection ?? config('database.default'));
            $user->forceFill([
                'name' => (string) ($account['name'] ?? $email),
                'email' => $email,
                'phone' => $account['phone'] ?? null,
                'password' => (string) $account['password'],
                'modules' => $account['modules'] ?? null,
                'school_id' => $schoolId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            $user->saveQuietly();
            $user->syncRoles(array_values(array_intersect((array) ($account['roles'] ?? []), array_diff($roles, SyncServer::NOT_STAFF))));
        }
    }

    /** Unlinks this computer. Its records stay; the sync records go. */
    /**
     * Stops syncing. Like a git clone that keeps its history, the computer
     * remembers which records it has synced (sync_rows), so linking it
     * again to the same school sends and asks about nothing twice.
     */
    public function unlink(): void
    {
        $db = DB::connection($this->connection);
        $db->table('app_state')->where('key', 'like', 'sync.%')->delete();
        $db->table('sync_inbox')->delete();
        $db->table('sync_reviews')->where('status', 'open')->delete();
    }

    /**
     * Linked to a school: the history is kept for the same school, and
     * started afresh for another one (whose records it has never seen).
     */
    protected function linkedTo(string $school): void
    {
        $db = DB::connection($this->connection);
        $before = $db->table('app_state')->where('key', self::HISTORY)->value('value');

        if ($before !== null && $before !== $school) {
            $db->table('sync_rows')->delete();
        }

        $db->table('app_state')->updateOrInsert(['key' => self::HISTORY], ['value' => $school, 'updated_at' => now()]);
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
            $this->put(self::RUNNING, now()->toIso8601String());
            $this->forget(self::WANTED);

            try {
                return $this->runNow();
            } finally {
                $this->forget(self::RUNNING);
            }
        });
    }

    /** @return array{received: int, sent: int, review: int, waiting: int} */
    protected function runNow(): array
    {
        $db = $this->database();
        $received = $this->fetch($db);
        $this->settleSameRecords($db);
        $this->applyInbox($db);
        $sent = $this->send($db);
        $this->settleSameRecords($db);

        // A payment or expense deleted here: fetch its online copy now,
        // so the review list shows exactly what was deleted.
        if ($db->db()->table('sync_reviews')->where('status', 'open')->where('kind', 'deleted_here')->whereNull('remote_data')->exists()) {
            $this->fetch($db);
        }

        $result = [
            'received' => $received,
            'sent' => $sent,
            'review' => $db->db()->table('sync_reviews')->where('status', 'open')->count(),
            'waiting' => $db->db()->table('sync_inbox')->count(),
        ];

        $this->put(self::LAST, now()->toIso8601String());
        $this->put(self::RESULT, (string) json_encode($result));
        $this->forget(self::ERROR);
        $this->forget(self::OFFLINE);
        $this->forget(self::FIRST);

        return $result;
    }

    /**
     * Sync as soon as possible without making anyone wait: in the Windows
     * app's background process (NativePHP), or by the scheduler within a
     * minute (sync:run). The screen is never held up by a sync.
     */
    public function requestSync(): void
    {
        $this->put(self::WANTED, now()->toIso8601String());

        $process = 'Native\\Desktop\\Facades\\ChildProcess';

        if (Edition::isDesktop() && class_exists($process)) {
            try {
                $process::artisan('sync:run', 'sync-now');
            } catch (\Throwable) {
                // The scheduler starts it within a minute.
            }
        }
    }

    /** Whether the background sync (sync:run, every minute) should sync now. */
    public function isDue(): bool
    {
        if (! $this->isLinked()) {
            return false;
        }

        $last = $this->state(self::LAST);

        return $this->state(self::WANTED) !== null || $last === null
            || ! CarbonImmutable::parse($last)->addMinutes((int) config('sync.every_minutes', 5))->isFuture();
    }

    /**
     * What the Windows app shows about syncing (top bar dot, Sync page).
     *
     * @return array{linked: bool, state: 'off'|'syncing'|'offline'|'error'|'review'|'ok', last: CarbonImmutable|null, error: string|null, reviews: int}
     */
    public function status(): array
    {
        $states = DB::connection($this->connection)->table('app_state')->where('key', 'like', 'sync.%')->pluck('value', 'key');
        $linked = $states->has(self::TOKEN);
        $running = $states->get(self::RUNNING);
        $reviews = $linked ? DB::connection($this->connection)->table('sync_reviews')->where('status', 'open')->count() : 0;

        $state = match (true) {
            ! $linked => 'off',
            // A sync that started over ten minutes ago has stopped (the app was closed).
            ($running !== null && CarbonImmutable::parse((string) $running)->addMinutes(10)->isFuture()) || $states->has(self::WANTED) => 'syncing',
            $states->has(self::OFFLINE) => 'offline',
            $states->has(self::ERROR) => 'error',
            $reviews > 0 => 'review',
            default => 'ok',
        };

        return [
            'linked' => $linked,
            'state' => $state,
            'last' => $states->has(self::LAST) ? CarbonImmutable::parse((string) $states->get(self::LAST)) : null,
            'error' => $states->get(self::ERROR),
            'reviews' => $reviews,
        ];
    }

    /** Records how a background sync went (sync:run). */
    public function failed(string $state, ?string $message = null): void
    {
        if ($state === self::OFFLINE) {
            if ($this->state(self::OFFLINE) === null) {
                $this->put(self::OFFLINE, now()->toIso8601String());
            }

            return;
        }

        $this->put(self::ERROR, (string) $message);
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
            $page = $this->transport->post('pull', ['cursor' => $cursor, 'scan' => $scan, 'fetch' => $fetch, 'school' => $computer, 'machine' => Machine::id()], $this->token());

            if ($scan && is_array($page['accounts'] ?? null)) {
                $this->keepAccounts($page['accounts']);
            }

            // Paid online: the licence for this computer comes with the first page.
            if ($scan && $school !== null && is_string($page['licence'] ?? null)) {
                DesktopLicence::keepFromOnline($school, $page['licence']);
            }

            $scan = false;
            $fetch = [];

            // A page at a time in one transaction: quick on the disk, and
            // the app is never kept waiting on a sync for more than a moment.
            $db->db()->transaction(function () use ($db, $page, &$received, &$cursor): void {
                foreach ((array) ($page['changes'] ?? []) as $change) {
                    $this->receive($db, (array) $change);
                    $received++;
                }

                $cursor = (int) ($page['cursor'] ?? $cursor);
                $this->put(self::CURSOR, (string) $cursor);
            });
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

        // In batches, each in one transaction (see fetch).
        foreach ($upserts->chunk(self::APPLY_BATCH) as $batch) {
            $db->db()->transaction(function () use ($db, $batch): void {
                foreach ($batch as $item) {
                    $this->applyUpsert($db, $item);
                }
            });
        }

        foreach ($deletes->chunk(self::APPLY_BATCH) as $batch) {
            $db->db()->transaction(function () use ($db, $batch): void {
                foreach ($batch as $item) {
                    $this->applyDelete($db, $item);
                }
            });
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

        if ($db->mustKeep($table, (int) $localId)) {
            // A learner with fees records here is never deleted: it goes back online.
            $db->db()->table('sync_rows')->where('uuid', $uuid)->update(['hash' => null]);
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
            $results = (array) ($this->transport->post('push', ['cursor' => $cursor, 'changes' => $batch, 'machine' => Machine::id()], $this->token())['results'] ?? []);

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
        $same = SyncTables::rules($table)['same'] ?? static::key($table, $row);

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

    /**
     * A review table's key columns, when the record has them all filled in
     * (SyncTables `key`): equal on both sides, it is one record already.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>|null
     */
    protected static function key(string $table, array $row): ?array
    {
        $key = SyncTables::rules($table)['key'] ?? null;

        if ($key === null) {
            return null;
        }

        foreach ($key as $column) {
            if (blank($row[$column] ?? null)) {
                return null;
            }
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    protected static function sameKey(string $table, array $a, array $b): bool
    {
        $key = static::key($table, $a);

        if ($key === null || static::key($table, $b) === null) {
            return false;
        }

        $plain = fn (mixed $value): string => is_numeric($value)
            ? (string) (float) $value
            : mb_strtolower(trim(substr((string) $value, 0, preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value) ? 10 : null)));

        foreach ($key as $column) {
            if ($plain($a[$column]) !== $plain($b[$column])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Possible duplicates that are in fact one record on both sides (same
     * key: a computer linked again after it forgot what it had synced, as
     * versions before 1.3.5 did when unlinked): joined up without asking,
     * keeping the latest edit.
     */
    protected function settleSameRecords(SyncDatabase $db): void
    {
        $reviews = $db->db()->table('sync_reviews')->where('status', 'open')
            ->whereIn('kind', ['duplicate', 'number', 'payment', 'entry'])
            ->whereIn('table', array_keys(array_filter(SyncTables::TABLES, fn (array $rules): bool => isset($rules['key']))))
            ->whereNotNull('local_id')
            ->get();

        foreach ($reviews as $review) {
            $table = (string) $review->table;
            $here = $db->find($table, (int) $review->local_id);

            if ($review->remote_data !== null) {
                [$other, $waiting] = $db->decode($table, (array) json_decode((string) $review->remote_data, true));
                $other = $waiting === null ? $other : null;
            } else {
                $otherId = $review->remote_uuid !== null ? $db->idOf($table, (string) $review->remote_uuid) : null;
                $other = $otherId !== null ? $db->find($table, $otherId) : null;
            }

            if ($here === null || $other === null || ! static::sameKey($table, $here, (array) $other)) {
                continue;
            }

            $newerOnline = SyncServer::isNewer(((array) $other)['updated_at'] ?? null, $here['updated_at'] ?? null);

            try {
                app(SyncReviews::class)->decide((int) $review->id, $newerOnline ? 'online' : 'offline', 'Sync: same record on both sides');
            } catch (RuntimeException) {
                // Left on the list for someone to decide.
            }
        }
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

    /**
     * The link's token. One this computer can no longer read (saved by a
     * version before DesktopKey, under the installer's key) is dropped, so
     * the Sync page asks for a new link code.
     */
    protected function token(): string
    {
        try {
            return Crypt::decryptString((string) $this->state(self::TOKEN));
        } catch (DecryptException) {
            DB::connection($this->connection)->table('app_state')->where('key', self::TOKEN)->delete();

            throw new RuntimeException('This computer needs linking again after the update. Online, open Windows app and make a new link code, then type it in below. Nothing on this computer is lost.');
        }
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

    protected function forget(string $key): void
    {
        DB::connection($this->connection)->table('app_state')->where('key', $key)->delete();
    }
}
