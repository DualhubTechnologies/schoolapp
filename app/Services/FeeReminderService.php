<?php

namespace App\Services;

use App\Models\FeeReminder;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fee reminders to guardians, by SMS or printed letter.
 *
 * Only students who actually owe money are reminded, and by default no
 * one gets a second SMS within a few days of the last -- a parent who has
 * just been reminded should not be chased again tomorrow.
 */
class FeeReminderService
{
    public const DEFAULT_TEMPLATE = 'Dear {guardian}, {student} ({class}) has a school fees balance of UGX {balance}. '
        . 'Kindly clear it by {deadline}. Thank you. {school}';

    public const PLACEHOLDERS = [
        '{guardian}' => 'Guardian\'s name',
        '{student}' => 'Student\'s name',
        '{adm}' => 'Admission number',
        '{class}' => 'Class',
        '{balance}' => 'Balance owed',
        '{term}' => 'Current term',
        '{deadline}' => 'Pay-by date',
        '{school}' => 'School name',
    ];

    public function __construct(protected SmsSender $sms) {}

    /**
     * Send an SMS reminder to the guardian of each student who owes.
     *
     * @param  Collection<int, Student>  $students
     * @return array{sent: int, failed: int, no_phone: int, not_owing: int, recently: int}
     */
    public function sendSms(Collection $students, ?string $template = null, ?string $deadline = null, int $skipIfRemindedWithinDays = 3): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'no_phone' => 0, 'not_owing' => 0, 'recently' => 0];
        $term = Term::current();

        foreach ($students as $student) {
            $student->loadMissing(['guardian', 'schoolClass', 'school']);
            $balance = $student->balance();

            if ($balance <= 0) {
                $result['not_owing']++;

                continue;
            }

            if ($skipIfRemindedWithinDays > 0 && $this->remindedRecently($student, $skipIfRemindedWithinDays)) {
                $result['recently']++;

                continue;
            }

            $phone = $this->phoneFor($student);

            if (! $phone) {
                $result['no_phone']++;

                continue;
            }

            $message = $this->message($student, $balance, $template, $deadline, $term);
            $outcome = $this->sms->send($phone, $message);

            FeeReminder::create([
                'school_id' => $student->school_id,
                'student_id' => $student->getKey(),
                'term_id' => $term?->getKey(),
                'channel' => 'sms',
                'phone' => $phone,
                'balance' => $balance,
                'message' => $message,
                'status' => $outcome['ok'] ? 'sent' : 'failed',
                'error' => $outcome['error'],
                'provider_ref' => $outcome['ref'],
                'sent_by' => auth()->user()?->name,
            ]);

            $result[$outcome['ok'] ? 'sent' : 'failed']++;
        }

        return $result;
    }

    /**
     * Record that reminder letters were printed for these students, and
     * return the letters' content for the print view.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array{student: Student, balance: float, message: string}>
     */
    public function letters(Collection $students, ?string $deadline = null): Collection
    {
        $term = Term::current();

        return $students
            ->each->loadMissing(['guardian', 'schoolClass', 'section', 'school'])
            ->map(fn (Student $student) => ['student' => $student, 'balance' => $student->balance()])
            ->filter(fn (array $row) => $row['balance'] > 0)
            ->map(function (array $row) use ($term, $deadline) {
                $row['deadline'] = $this->deadlineText($deadline);
                $row['term'] = $term;

                FeeReminder::create([
                    'school_id' => $row['student']->school_id,
                    'student_id' => $row['student']->getKey(),
                    'term_id' => $term?->getKey(),
                    'channel' => 'letter',
                    'balance' => $row['balance'],
                    'message' => 'Printed reminder letter',
                    'status' => 'printed',
                    'sent_by' => auth()->user()?->name,
                ]);

                return $row;
            })
            ->values();
    }

    public function message(Student $student, float $balance, ?string $template = null, ?string $deadline = null, ?Term $term = null): string
    {
        $guardian = $student->guardian?->name ?: 'Parent/Guardian';

        return strtr($template ?: self::DEFAULT_TEMPLATE, [
            '{guardian}' => $this->firstWords($guardian, 2),
            '{student}' => $this->firstWords((string) $student->name, 2),
            '{adm}' => (string) $student->admission_no,
            '{class}' => (string) $student->schoolClass?->name,
            '{balance}' => number_format($balance, 0),
            '{term}' => $term?->label() ?? '',
            '{deadline}' => $this->deadlineText($deadline),
            '{school}' => (string) $student->school?->name,
        ]);
    }

    /**
     * Best number to reach the family: guardian's phone, their alternative
     * number, then the student's own.
     */
    public function phoneFor(Student $student): ?string
    {
        foreach ([$student->guardian?->phone, $student->guardian?->alt_phone, $student->phone] as $candidate) {
            if ($normalised = SmsSender::normalisePhone($candidate)) {
                return $normalised;
            }
        }

        return null;
    }

    public function remindedRecently(Student $student, int $days): bool
    {
        return FeeReminder::where('student_id', $student->getKey())
            ->where('channel', 'sms')
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subDays($days))
            ->exists();
    }

    protected function deadlineText(?string $deadline): string
    {
        return $deadline ? Carbon::parse($deadline)->format('j M Y') : 'the earliest opportunity';
    }

    protected function firstWords(string $text, int $count): string
    {
        return implode(' ', array_slice(preg_split('/\s+/', trim($text)) ?: [], 0, $count));
    }
}
