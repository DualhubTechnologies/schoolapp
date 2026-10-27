<?php

namespace App\Services\Sync;

use App\Models\School;
use App\Models\SyncDevice;
use App\Models\User;
use App\Support\Licensing\LicenceIssuer;
use App\Support\Modules;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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

    /**
     * A code for linking a Windows computer to the school: an hour when a
     * School Admin makes it online, longer when SchoolHub sends it.
     */
    public function makeLinkCode(School $school, ?string $by = null, int $hours = 1): string
    {
        $code = collect(range(1, 8))->map(fn (): string => self::CODE_LETTERS[random_int(0, strlen(self::CODE_LETTERS) - 1)])->implode('');
        $code = substr($code, 0, 4).'-'.substr($code, 4);

        SyncDevice::on($this->connection)->where('school_id', $school->id)->whereNull('token')->delete();
        SyncDevice::on($this->connection)->create([
            'school_id' => $school->id,
            'link_code' => $code,
            'link_expires_at' => now()->addHours($hours),
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
        $device = $this->waitingDevice($code);

        return ['token' => $this->issueToken($device, $name), 'school' => (string) $device->getConnection()->table('schools')->where('id', $device->school_id)->value('name')];
    }

    /**
     * A new Windows app joining an existing online school on its first run:
     * the school administrator's online email and password, and a link
     * code. Gives the token, the school's details (to set the school up the
     * same, licence code included) and the administrator's name.
     *
     * @return array{token: string, school: array<string, mixed>, user: array{name: string, email: string}}
     */
    public function connect(string $code, string $email, string $password, ?string $name): array
    {
        $device = $this->waitingDevice($code);

        $user = User::on($this->connection)->where('email', trim($email))->where('school_id', $device->school_id)->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            throw new RuntimeException('That email and password do not match an account of this school online.');
        }

        $roles = $device->getConnection()->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->getKey())
            ->pluck('roles.name')
            ->all();

        if (array_intersect($roles, Modules::FULL_ACCESS_ROLES) === []) {
            throw new RuntimeException('Only the school administrator can set up the Windows app with the online account.');
        }

        $school = (array) $device->getConnection()->table('schools')->where('id', $device->school_id)->first();

        return [
            'token' => $this->issueToken($device, $name),
            'school' => Arr::only($school, self::SCHOOL_DETAILS),
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
        ];
    }

    /** The school's details a new Windows app copies (no online-only settings or secrets). */
    public const SCHOOL_DETAILS = [
        'name', 'email', 'phone', 'address', 'city', 'country', 'timezone', 'currency', 'nssf_employer_number', 'tin_number',
        'motto', 'description', 'unique_code', 'website', 'school_type', 'boarding_type', 'ownership', 'contact_person', 'contact_title',
        'fee_payment_bank', 'fee_payment_mobile_money', 'fee_payment_instructions', 'parent_sms_language',
        'admission_no_style', 'admission_no_initials', 'admission_no_start', 'fee_sheet_notes',
    ];

    protected function waitingDevice(string $code): SyncDevice
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        $code = substr($code, 0, 4).'-'.substr($code, 4);

        $device = SyncDevice::on($this->connection)->where('link_code', $code)->whereNull('token')->first();

        if (! $device || $device->link_expires_at?->isPast()) {
            throw new RuntimeException('That link code is wrong or has expired. Make a new one online under Settings → Windows app, or ask SchoolHub for one.');
        }

        return $device;
    }

    protected function issueToken(SyncDevice $device, ?string $name): string
    {
        $token = Str::random(48);
        $device->update([
            'name' => $name ? Str::limit($name, 100, '') : 'Windows computer',
            'token' => hash('sha256', $token),
            'link_code' => null,
            'link_expires_at' => null,
            'linked_at' => now(),
        ]);

        return $token;
    }

    public function device(?string $token): ?SyncDevice
    {
        return $token ? SyncDevice::on($this->connection)->where('token', hash('sha256', $token))->first() : null;
    }

    /**
     * The changes made online after `cursor`, a page at a time. The first
     * page (scan) first records what changed online since the last time.
     * `fetch` asks again for particular records (by uuid). With the
     * computer's own school name and code (`computer`), the first page also
     * carries its licence from the online subscription (LicenceIssuer).
     *
     * @param  list<string>  $fetch
     * @param  array{name?: string, code?: string}  $computer
     * @return array{cursor: int, more: bool, changes: list<array<string, mixed>>, licence?: string|null}
     */
    public function pull(SyncDevice $device, int $cursor, bool $scan = true, array $fetch = [], array $computer = []): array
    {
        return $this->locked($device, function () use ($device, $cursor, $scan, $fetch, $computer): array {
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

            $page = ['cursor' => max($cursor, $next), 'more' => $more, 'changes' => $this->describe($db, $entries->all())];

            if ($scan && filled($computer['name'] ?? null) && filled($computer['code'] ?? null) && ($school = $device->school) !== null) {
                $page['licence'] = app(LicenceIssuer::class)->forOnlineAccount($school, (string) $computer['name'], (string) $computer['code']);
            }

            return $page;
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
