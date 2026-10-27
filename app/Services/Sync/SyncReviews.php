<?php

namespace App\Services\Sync;

use App\Models\School;
use App\Support\AdmissionNumbers;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;
use stdClass;

/**
 * The Windows app's sync review list: what each item shows and what each
 * decision does. An item compares a record on this computer with one from
 * online:
 *
 *   duplicate      the same learner or staff member added on both sides?
 *   number         two people with the same admission or staff number
 *   payment/entry  a payment, income or expense of the same amount on the
 *                  same date on both sides, whatever the receipt numbers
 *   deleted_there  a payment or expense deleted online: delete it here?
 *   deleted_here   one deleted here: delete it online too?
 *   clash          could not be saved (its name or number is taken)
 *
 * Joining two records keeps the one on this computer, with the details
 * chosen, so everything linked to it (payments, marks) stays linked; the
 * other copy is deleted online, after what pointed to it there is moved.
 */
class SyncReviews
{
    public function __construct(protected SyncClient $client) {}

    /** @return array<string, string> decision => button label */
    public function options(stdClass $review): array
    {
        $label = SyncTables::label((string) $review->table);
        $hasOnline = $review->remote_data !== null || $review->kind !== 'deleted_here';

        return match ((string) $review->kind) {
            'duplicate' => ['online' => "Same {$label}: keep the online details", 'offline' => "Same {$label}: keep this computer's details", 'both' => 'Different: keep both'],
            'number' => ['renumber' => 'Different: give the one on this computer a new number', 'online' => "Same {$label}: keep the online details", 'offline' => "Same {$label}: keep this computer's details"],
            'payment', 'entry' => ['online' => 'Same: keep the online one', 'offline' => "Same: keep this computer's one", 'both' => 'Different: keep both'],
            'deleted_there' => ['delete' => 'Delete it here too', 'keep' => 'Keep it, and put it back online'],
            'deleted_here' => $hasOnline ? ['delete' => 'Delete it online too', 'restore' => 'Bring it back on this computer'] : ['delete' => 'Delete it online too'],
            default => ['retry' => 'I have corrected it: try again'],
        };
    }

    public function title(stdClass $review): string
    {
        $label = SyncTables::label((string) $review->table);

        return match ((string) $review->kind) {
            'duplicate' => "Is this the same {$label}?",
            'number' => "Two {$label}s with the same number",
            'payment' => 'A similar payment was recorded on both sides',
            'entry' => 'A similar income or expense was recorded on both sides',
            'deleted_there' => ucfirst($label).' deleted online',
            'deleted_here' => ucfirst($label).' deleted on this computer',
            default => "This {$label} could not be synced: another one has the same name or number. Change it on one side, then try again.",
        };
    }

    /**
     * The two records side by side.
     *
     * @return array{here: array<string, string>|null, online: array<string, string>|null}
     */
    public function compare(stdClass $review): array
    {
        $db = $this->client->database();
        $table = (string) $review->table;

        $here = $review->local_id !== null ? $db->find($table, (int) $review->local_id) : null;
        $online = $review->remote_data !== null
            ? $this->readable($db, $table, (array) json_decode((string) $review->remote_data, true))
            : ($review->remote_uuid !== null && in_array($review->kind, ['duplicate', 'number', 'payment', 'entry'], true)
                ? $db->find($table, (int) $db->idOf($table, (string) $review->remote_uuid))
                : null);

        return [
            'here' => $here !== null ? $this->details($db, $table, $here) : null,
            'online' => $online !== null ? $this->details($db, $table, $online) : null,
        ];
    }

    /** Carries out a decision, then the record syncs at the next sync. */
    public function decide(int $reviewId, string $decision, ?string $by = null): void
    {
        $db = $this->client->database();
        $review = $db->db()->table('sync_reviews')->where('id', $reviewId)->where('status', 'open')->first();

        if ($review === null || ! array_key_exists($decision, $this->options($review))) {
            throw new RuntimeException('That choice is no longer available.');
        }

        $db->db()->transaction(function () use ($db, $review, $decision, $by): void {
            $table = (string) $review->table;
            $localId = $review->local_id !== null ? (int) $review->local_id : null;
            $uuid = $review->remote_uuid !== null ? (string) $review->remote_uuid : null;
            $data = $review->remote_data !== null ? (array) json_decode((string) $review->remote_data, true) : null;

            match ((string) $review->kind) {
                'duplicate', 'number', 'payment', 'entry' => $data !== null
                    ? $this->decideIncoming($db, $table, (int) $localId, (string) $uuid, $data, $decision)
                    : $this->decideNewHere($db, $table, (int) $localId, (string) $uuid, $decision),
                'deleted_there' => $this->decideDeletedThere($db, $table, (string) $uuid, $decision),
                'deleted_here' => $decision === 'delete'
                    ? $db->db()->table('sync_rows')->where('uuid', $uuid)->update(['delete_approved' => true])
                    : $db->save($table, (string) $uuid, (array) $data),
                default => null,
            };

            if ($uuid !== null && $review->kind !== 'clash') {
                $db->db()->table('sync_inbox')->where('uuid', $uuid)->delete();
            }

            $db->db()->table('sync_reviews')->where('id', $review->id)->update([
                'status' => 'done',
                'decision' => $decision,
                'decided_by' => $by,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * A record from online that looks like one here (L).
     *
     * @param  array<string, mixed>  $data
     */
    protected function decideIncoming(SyncDatabase $db, string $table, int $localId, string $uuid, array $data, string $decision): void
    {
        if ($decision === 'both' || $decision === 'renumber') {
            if ($decision === 'renumber') {
                $this->renumber($db, $table, $localId);
            }

            try {
                $db->save($table, $uuid, $data, ['origin' => 'there']);
            } catch (UniqueConstraintViolationException) {
                $this->renumber($db, $table, $localId);
                $db->save($table, $uuid, $data, ['origin' => 'there']);
            }

            // Checked: the one here goes online at the next sync without being asked about again.
            if ($db->uuidOf($table, $localId) === null) {
                $db->assign($table, $localId);
            }

            return;
        }

        // One record from now on: this computer's, under the online uuid.
        $old = $db->uuidOf($table, $localId);

        if ($old !== null && $old !== $uuid) {
            $db->db()->table('sync_rows')->where('uuid', $old)->update(['deleted' => true, 'delete_approved' => true]);
            $db->refresh();
            $this->resendLinked($db, $table, $localId);
        }

        if ($decision === 'online') {
            $db->overwrite($table, $localId, $data);
            $db->map($table, $localId, $uuid, ['hash' => $db->fingerprint((array) $db->find($table, $localId)), 'origin' => 'there']);
        } else {
            $db->map($table, $localId, $uuid, ['hash' => null, 'origin' => 'there']);
        }
    }

    /** A record added here (L) that looks like one that came from online (R). */
    protected function decideNewHere(SyncDatabase $db, string $table, int $localId, string $uuid, string $decision): void
    {
        $onlineId = $db->idOf($table, $uuid);

        if ($decision === 'both' || $decision === 'renumber' || $onlineId === null) {
            if ($decision === 'renumber') {
                $this->renumber($db, $table, $localId);
            }
            $db->assign($table, $localId);

            return;
        }

        $here = (array) $db->find($table, $localId);
        $this->move($db, $table, $localId, $onlineId);
        $db->db()->table($table)->where('id', $localId)->delete();

        if ($decision === 'offline') {
            $numbers = SyncTables::rules($table)['numbers']['column'] ?? null;
            $details = array_diff_key($here, array_flip(array_filter(['id', 'school_id', 'created_at', $numbers])));

            if (array_key_exists('updated_at', $details)) {
                $details['updated_at'] = now();
            }
            $db->db()->table($table)->where('id', $onlineId)->update($details);
        }
    }

    protected function decideDeletedThere(SyncDatabase $db, string $table, string $uuid, string $decision): void
    {
        if ($decision === 'keep') {
            $db->db()->table('sync_rows')->where('uuid', $uuid)->update(['hash' => null]);

            return;
        }

        try {
            $db->delete($table, $uuid);
        } catch (QueryException) {
            throw new RuntimeException('It cannot be deleted while other records here still use it.');
        }
    }

    /** Points everything linked to record `from` at record `to` instead. */
    protected function move(SyncDatabase $db, string $table, int $from, int $to): void
    {
        foreach ($this->linkedTables($db, $table) as [$child, $column]) {
            foreach ($db->db()->table($child)->where($column, $from)->pluck('id') as $childId) {
                try {
                    $db->db()->table($child)->where('id', $childId)->update([$column => $to]);
                } catch (UniqueConstraintViolationException) {
                    // `to` already has one (e.g. a mark for the same exam and subject).
                    $db->db()->table($child)->where('id', $childId)->delete();
                }
            }
        }
    }

    /** Makes the synced records linked to this one go online again. */
    protected function resendLinked(SyncDatabase $db, string $table, int $id): void
    {
        foreach ($this->linkedTables($db, $table) as [$child, $column]) {
            if (! in_array($child, SyncTables::names(), true)) {
                continue;
            }

            $ids = $db->db()->table($child)->where($column, $id)->pluck('id');
            $db->db()->table('sync_rows')->where('table', $child)->whereIn('row_id', $ids)->where('deleted', false)->update(['hash' => null]);
        }
    }

    /**
     * Every table and column that links to records of `table`.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function linkedTables(SyncDatabase $db, string $table): array
    {
        $links = [];

        foreach ($db->db()->getSchemaBuilder()->getTables() as $other) {
            foreach ($db->foreignKeys((string) $other['name']) as $column => $foreign) {
                if ($foreign === $table) {
                    $links[] = [(string) $other['name'], $column];
                }
            }
        }

        return $links;
    }

    /** A new admission or staff number for the record here. */
    protected function renumber(SyncDatabase $db, string $table, int $id): void
    {
        if ($table === 'students') {
            $school = School::on($db->connection)->findOrFail($db->schoolId);
            $db->db()->table('students')->where('id', $id)->update(['admission_no' => AdmissionNumbers::next($school)]);
        } elseif ($table === 'staff') {
            $number = (string) $db->db()->table('staff')->where('id', $id)->value('staff_no');
            $db->db()->table('staff')->where('id', $id)->update(['staff_no' => $number !== '' ? $number.'-W' : null]);
        }
    }

    /**
     * An online record's links turned into this side's ids where known,
     * only for showing it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function readable(SyncDatabase $db, string $table, array $data): array
    {
        foreach ($db->foreignKeys($table) as $column => $foreign) {
            $value = $data[$column] ?? null;
            $data[$column] = is_array($value) && ! empty($value['ref']) ? $db->idOf($foreign, (string) $value['ref']) : null;
        }

        return $data;
    }

    /**
     * What people need to tell two records apart.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    protected function details(SyncDatabase $db, string $table, array $row): array
    {
        $name = fn (string $other, mixed $id): string => $id ? (string) $db->db()->table($other)->where('id', $id)->value('name') : '-';
        $money = fn (mixed $amount): string => 'UGX '.number_format((float) $amount);
        $date = fn (mixed $value): string => $value ? now()->parse((string) $value)->format('j M Y') : '-';

        $details = match ($table) {
            'students' => [
                'Name' => (string) ($row['name'] ?? ''),
                'Class' => $name('school_classes', $row['school_class_id'] ?? null),
                'Admission no.' => (string) ($row['admission_no'] ?? '-'),
                'Parent\'s phone' => ($row['guardian_id'] ?? null) ? (string) $db->db()->table('guardians')->where('id', $row['guardian_id'])->value('phone') : '-',
            ],
            'staff' => [
                'Name' => (string) ($row['name'] ?? ''),
                'Staff no.' => (string) ($row['staff_no'] ?? '-'),
                'Position' => (string) ($row['position'] ?? '-'),
                'Phone' => (string) ($row['phone'] ?? '-'),
            ],
            'student_payments' => [
                'Receipt' => (string) ($row['receipt_no'] ?? '-'),
                'Learner' => $name('students', $row['student_id'] ?? null),
                'Amount' => $money($row['amount'] ?? 0),
                'Paid on' => $date($row['paid_on'] ?? null),
                'Method' => ucfirst(str_replace('_', ' ', (string) ($row['method'] ?? '-'))),
                'Recorded by' => (string) ($row['recorded_by'] ?? '-'),
            ],
            'finance_entries' => [
                'Voucher' => (string) ($row['voucher_no'] ?? '-'),
                'Type' => ucfirst((string) ($row['type'] ?? '')),
                'Category' => $name('finance_categories', $row['finance_category_id'] ?? null),
                'Amount' => $money($row['amount'] ?? 0),
                'Date' => $date($row['entry_date'] ?? null),
                'Paid to / by' => (string) ($row['party'] ?? '-'),
                'Details' => (string) ($row['description'] ?? '-'),
            ],
            default => array_filter([
                'Name' => isset($row['name']) ? (string) $row['name'] : null,
            ]),
        };

        $details['Last changed'] = $date($row['updated_at'] ?? null);

        return array_map(fn (string $value): string => $value !== '' ? $value : '-', $details);
    }
}
