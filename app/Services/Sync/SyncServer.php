<?php

namespace App\Services\Sync;

use App\Models\School;
use App\Models\SyncDevice;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

/**
 * The online side of syncing with the Windows app.
 *
 * Changes made online are found by comparing each record with its
 * fingerprint in sync_rows (scan), so every way of saving counts, imports
 * and bulk updates included. Each change gets the school's next version
 * number; a computer fetches the changes after the last version it has
 * (pull) and sends its own (push). A record changed both online and on
 * the computer since the computer last fetched keeps the latest edit.
 */
class SyncServer
{
    public const PAGE = 1000;

    /** Link codes: easy to read out and type, no 0/O or 1/I. */
    protected const CODE_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(protected ?string $connection = null) {}

    /** A one-hour code for linking a Windows computer to the school. */
    public function makeLinkCode(School $school, ?string $by = null): string
    {
        $code = collect(range(1, 8))->map(fn (): string => self::CODE_LETTERS[random_int(0, strlen(self::CODE_LETTERS) - 1)])->implode('');
        $code = substr($code, 0, 4).'-'.substr($code, 4);

        SyncDevice::on($this->connection)->where('school_id', $school->id)->whereNull('token')->delete();
        SyncDevice::on($this->connection)->create([
            'school_id' => $school->id,
            'link_code' => $code,
            'link_expires_at' => now()->addHour(),
            'created_by' => $by,
        ]);

        return $code;
    }

    /**
     * The computer swaps the link code for its own secret token.
     *
     * @return array{token: string, school: string}
     */
    public function link(string $code, ?string $name): array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        $code = substr($code, 0, 4).'-'.substr($code, 4);

        $device = SyncDevice::on($this->connection)->where('link_code', $code)->whereNull('token')->first();

        if (! $device || $device->link_expires_at?->isPast()) {
            throw new RuntimeException('That link code is wrong or has expired. Make a new one online under Settings → Windows app.');
        }

        $token = Str::random(48);
        $device->update([
            'name' => $name ? Str::limit($name, 100, '') : 'Windows computer',
            'token' => hash('sha256', $token),
            'link_code' => null,
            'link_expires_at' => null,
            'linked_at' => now(),
        ]);

        return ['token' => $token, 'school' => (string) $device->getConnection()->table('schools')->where('id', $device->school_id)->value('name')];
    }

    public function device(?string $token): ?SyncDevice
    {
        return $token ? SyncDevice::on($this->connection)->where('token', hash('sha256', $token))->first() : null;
    }

    /**
     * The changes made online after `cursor`, a page at a time. The first
     * page (scan) first records what changed online since the last time.
     * `fetch` asks again for particular records (by uuid).
     *
     * @param  list<string>  $fetch
     * @return array{cursor: int, more: bool, changes: list<array<string, mixed>>}
     */
    public function pull(SyncDevice $device, int $cursor, bool $scan = true, array $fetch = []): array
    {
        return $this->locked($device, function () use ($device, $cursor, $scan, $fetch): array {
            $db = new SyncDatabase($device->school_id, $this->connection);

            if ($scan) {
                $this->scan($db);
            }

            $entries = $db->db()->table('sync_rows')
                ->where('school_id', $device->school_id)
                ->where('version', '>', $cursor)
                ->where(fn ($query) => $query->whereNull('source_device_id')->orWhere('source_device_id', '!=', $device->id))
                ->orderBy('version')
                ->limit(self::PAGE + 1)
                ->get();

            $more = $entries->count() > self::PAGE;
            $entries = $entries->take(self::PAGE);

            if ($fetch !== []) {
                $entries = $entries->merge($db->db()->table('sync_rows')->where('school_id', $device->school_id)->whereIn('uuid', $fetch)->get());
            }

            $next = $more
                ? (int) $entries->take(self::PAGE)->max('version')
                : (int) $db->db()->table('sync_rows')->where('school_id', $device->school_id)->max('version');

            $device->update(['last_synced_at' => now()]);

            return ['cursor' => max($cursor, $next), 'more' => $more, 'changes' => $this->describe($db, $entries->all())];
        });
    }

    /**
     * Saves the computer's changes. Each comes back "ok", "kept_online"
     * (changed online more recently), "waiting" (links to a record not
     * here yet) or "clash" (another record already has its name/number).
     *
     * @param  list<array<string, mixed>>  $changes
     * @return array<string, string> uuid => result
     */
    public function push(SyncDevice $device, int $cursor, array $changes): array
    {
        return $this->locked($device, function () use ($device, $cursor, $changes): array {
            $db = new SyncDatabase($device->school_id, $this->connection);
            $this->scan($db);
            $db->refresh();

            $version = (int) $db->db()->table('sync_rows')->where('school_id', $device->school_id)->max('version');
            $results = [];

            foreach ($changes as $change) {
                $table = (string) ($change['t'] ?? '');
                $uuid = (string) ($change['u'] ?? '');

                if (! in_array($table, SyncTables::names(), true) || ! Str::isUuid($uuid)) {
                    continue;
                }

                $entry = $db->db()->table('sync_rows')->where('uuid', $uuid)->first();

                if ($entry && (int) $entry->school_id !== $device->school_id) {
                    $results[$uuid] = 'clash';

                    continue;
                }

                try {
                    if (! empty($change['d'])) {
                        if ($entry && ! $entry->deleted) {
                            $db->db()->table($table)->where('id', $entry->row_id)->delete();
                            $db->db()->table('sync_rows')->where('uuid', $uuid)->update(['deleted' => true, 'version' => ++$version, 'source_device_id' => $device->id, 'changed_at' => now()]);
                        }
                        $results[$uuid] = 'ok';

                        continue;
                    }

                    if ($entry && ! $entry->deleted && $entry->version > $cursor && (int) $entry->source_device_id !== $device->id) {
                        $online = $db->find($table, (int) $entry->row_id);

                        if ($online && self::isNewer($online['updated_at'] ?? $entry->changed_at, $change['at'] ?? null)) {
                            $results[$uuid] = 'kept_online';

                            continue;
                        }
                    }

                    $db->save($table, $uuid, (array) ($change['data'] ?? []), [
                        'version' => ++$version,
                        'source_device_id' => $device->id,
                        'origin' => $entry->origin ?? 'there',
                        'changed_at' => now(),
                    ]);
                    $results[$uuid] = 'ok';
                } catch (SyncWaiting) {
                    $results[$uuid] = 'waiting';
                } catch (UniqueConstraintViolationException) {
                    $results[$uuid] = 'clash';
                } catch (QueryException) {
                    // e.g. deleting a record others still point to: they come first next time.
                    $results[$uuid] = 'waiting';
                }
            }

            $device->update(['last_synced_at' => now()]);

            return $results;
        });
    }

    /**
     * Records what changed online since the last scan: new, changed and
     * deleted records each get the next version number.
     */
    public function scan(SyncDatabase $db): void
    {
        $version = (int) $db->db()->table('sync_rows')->where('school_id', $db->schoolId)->max('version');
        $now = now();

        foreach (SyncTables::names() as $table) {
            $known = $db->db()->table('sync_rows')
                ->where('school_id', $db->schoolId)
                ->where('table', $table)
                ->where('deleted', false)
                ->get(['id', 'row_id', 'hash'])
                ->keyBy('row_id');

            $new = [];
            $seen = [];

            foreach ($db->query($table)->orderBy("{$table}.id")->select("{$table}.*")->cursor() as $row) {
                $row = (array) $row;
                $id = (int) $row['id'];
                $hash = $db->fingerprint($row);
                $seen[$id] = true;
                $entry = $known->get($id);

                if ($entry === null) {
                    $new[] = ['school_id' => $db->schoolId, 'table' => $table, 'row_id' => $id, 'uuid' => (string) Str::uuid(), 'hash' => $hash, 'version' => ++$version, 'origin' => 'here', 'deleted' => false, 'delete_approved' => false, 'changed_at' => $now];
                } elseif ($entry->hash !== $hash) {
                    $db->db()->table('sync_rows')->where('id', $entry->id)->update(['hash' => $hash, 'version' => ++$version, 'source_device_id' => null, 'changed_at' => $now]);
                }
            }

            foreach (array_chunk($new, 500) as $chunk) {
                $db->db()->table('sync_rows')->insert($chunk);
            }

            foreach ($known as $rowId => $entry) {
                if (! isset($seen[(int) $rowId])) {
                    $db->db()->table('sync_rows')->where('id', $entry->id)->update(['deleted' => true, 'version' => ++$version, 'source_device_id' => null, 'changed_at' => $now]);
                }
            }
        }

        $db->refresh();
    }

    /**
     * sync_rows entries as changes to send.
     *
     * @param  array<int, stdClass>  $entries
     * @return list<array<string, mixed>>
     */
    protected function describe(SyncDatabase $db, array $entries): array
    {
        $rows = collect($entries)
            ->reject(fn (stdClass $entry): bool => (bool) $entry->deleted)
            ->groupBy('table')
            ->map(fn ($group, string $table) => $db->db()->table($table)->whereIn('id', $group->pluck('row_id'))->get()->keyBy('id'));

        $changes = [];

        foreach ($entries as $entry) {
            $row = $entry->deleted ? null : ($rows[$entry->table][$entry->row_id] ?? null);

            $changes[] = [
                't' => $entry->table,
                'u' => $entry->uuid,
                'v' => (int) $entry->version,
                'd' => $row === null,
                'at' => $row !== null ? ($row->updated_at ?? $entry->changed_at) : $entry->changed_at,
                'data' => $row !== null ? $db->encode($entry->table, (array) $row) : null,
            ];
        }

        return $changes;
    }

    /** Whether time `a` is later than time `b` (a missing time is the oldest). */
    public static function isNewer(mixed $a, mixed $b): bool
    {
        if (blank($a)) {
            return false;
        }

        if (blank($b)) {
            return true;
        }

        return CarbonImmutable::parse((string) $a)->greaterThan(CarbonImmutable::parse((string) $b));
    }

    /**
     * One sync at a time per school.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function locked(SyncDevice $device, callable $callback): mixed
    {
        return Cache::lock('sync-school-'.$device->school_id, 300)->block(60, $callback);
    }
}
