<?php

namespace App\Services\Messaging;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Models\MessageBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Services\FeeReminderService;
use App\Services\SmsSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who a bulk SMS goes to, and the text each one receives.
 *
 * Messages to families go to the best number for each learner (guardian,
 * their other number, then the learner's own). A family with several
 * children gets one SMS -- unless the message names the learner
 * ({student}, {class}, {balance}), when each child gets their own.
 */
class BulkMessages
{
    public const PLACEHOLDERS = [
        '{student}' => "Learner's name",
        '{class}' => 'Class',
        '{balance}' => 'Fees balance',
        '{school}' => 'School name',
    ];

    public function __construct(protected FeeReminderService $reminders) {}

    /**
     * @param  array{class_id?: int|null, section_id?: int|null}  $filters
     * @return array{recipients: Collection<int, MessageRecipient>, no_phone: int}
     */
    public function recipients(School $school, string $audience, array $filters, string $body): array
    {
        $recipients = collect();
        $noPhone = 0;

        if ($audience === 'staff') {
            foreach (Staff::where('school_id', $school->id)->where('status', 'active')->orderBy('name')->get() as $staff) {
                $phone = SmsSender::normalisePhone($staff->phone);
                $phone ? $recipients->push(new MessageRecipient($phone, (string) $staff->name)) : $noPhone++;
            }

            return ['recipients' => $this->unique($recipients), 'no_phone' => $noPhone];
        }

        foreach ($this->students($school, $audience, $filters) as $student) {
            $phone = $this->reminders->phoneFor($student);
            $phone ? $recipients->push(new MessageRecipient($phone, (string) $student->name, $student)) : $noPhone++;
        }

        return ['recipients' => $this->isPersonal($body) ? $recipients->values() : $this->unique($recipients), 'no_phone' => $noPhone];
    }

    /**
     * The learners an audience covers.
     *
     * @param  array{class_id?: int|null, section_id?: int|null}  $filters
     * @return Collection<int, Student>
     */
    public function students(School $school, string $audience, array $filters): Collection
    {
        $query = Student::where('students.school_id', $school->id)
            ->where('students.status', 'active')
            ->with(['guardian', 'schoolClass'])
            ->orderBy('students.name');

        if (in_array($audience, ['class', 'owing'], true)) {
            $query->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('students.school_class_id', $id))
                ->when($filters['section_id'] ?? null, fn ($q, $id) => $q->where('students.section_id', $id));
        }

        if ($audience === 'class' && empty($filters['class_id'])) {
            return collect();
        }

        if ($audience === 'owing') {
            $query->select('students.*')
                ->where(DB::raw('('.FeeBalanceResource::chargedSql().' - '.FeeBalanceResource::paidSql().')'), '>', 0);
        }

        return $query->get()->toBase();
    }

    /**
     * "Parents of P.4 East", for the history list.
     *
     * @param  array{class_id?: int|null, section_id?: int|null}  $filters
     */
    public function label(string $audience, array $filters): string
    {
        $class = empty($filters['class_id']) ? '' : (string) SchoolClass::whereKey($filters['class_id'])->value('name');
        $section = empty($filters['section_id']) ? '' : (string) Section::whereKey($filters['section_id'])->value('name');
        $where = trim("{$class} {$section}");

        return match ($audience) {
            'staff' => 'All staff',
            'class' => 'Parents of '.($where ?: 'a class'),
            'owing' => 'Parents owing fees'.($where ? " in {$where}" : ''),
            default => 'Parents of all learners',
        };
    }

    public function isPersonal(string $body): bool
    {
        return (bool) preg_match('/\{(student|class|balance)\}/', $body);
    }

    /**
     * The text for one recipient, placeholders filled in.
     */
    public function render(string $body, School $school, MessageRecipient $recipient): string
    {
        $student = $recipient->student;

        return strtr($body, [
            '{school}' => (string) $school->name,
            '{student}' => $recipient->name,
            '{class}' => $student->schoolClass->name ?? '',
            '{balance}' => $student ? 'UGX '.number_format(max(0, $student->balance())) : '',
        ]);
    }

    /**
     * How many SMS a text takes: 160 characters for one, 153 for each
     * part of a longer one.
     */
    public static function parts(string $text): int
    {
        $length = mb_strlen($text);

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    public function send(MessageBatch $batch): void
    {
        $school = $batch->school;
        ['recipients' => $recipients, 'no_phone' => $noPhone] = $this->recipients($school, $batch->audience, $batch->filters ?? [], $batch->body);

        $batch->update(['status' => 'sending', 'recipients' => $recipients->count(), 'no_phone' => $noPhone]);
        $sms = app(SmsSender::class);
        $sent = $failed = 0;

        foreach ($recipients as $i => $recipient) {
            $sms->send($recipient->phone, $this->render($batch->body, $school, $recipient))['ok'] ? $sent++ : $failed++;

            // Progress for the history list on long sends.
            if ($i % 25 === 24) {
                $batch->update(['sent' => $sent, 'failed' => $failed]);
            }
        }

        $batch->update(['status' => 'done', 'sent' => $sent, 'failed' => $failed, 'finished_at' => now()]);
    }

    /**
     * One entry per phone number.
     *
     * @param  Collection<int, MessageRecipient>  $recipients
     * @return Collection<int, MessageRecipient>
     */
    protected function unique(Collection $recipients): Collection
    {
        return $recipients->unique(fn (MessageRecipient $r) => $r->phone)->values();
    }
}
