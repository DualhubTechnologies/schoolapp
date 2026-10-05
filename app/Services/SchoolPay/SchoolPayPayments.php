<?php

namespace App\Services\SchoolPay;

use App\Models\School;
use App\Models\SchoolPayTransaction;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Term;
use Illuminate\Database\UniqueConstraintViolationException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Turns the payments SchoolPay reports (web hook or nightly check) into
 * SchoolHub receipts.
 *
 * - Each SchoolPay receipt number is taken once, however often it is
 *   reported.
 * - School fees go to the learner whose SchoolPay code matches, else whose
 *   admission number matches SchoolPay's registration number, and become a
 *   "SchoolPay" receipt for the current term. With no match they wait on
 *   the SchoolPay payments page until the bursar picks the learner.
 * - Other fees (uniform, trips...) are listed there but not added to the
 *   learner's fees.
 */
class SchoolPayPayments
{
    public function __construct(protected SchoolPayClient $client) {}

    /**
     * @param  array<mixed>  $payment  one payment as SchoolPay sends it
     */
    public function ingest(School $school, array $payment, string $type, string $source): ?SchoolPayTransaction
    {
        $receipt = trim((string) ($payment['schoolpayReceiptNumber'] ?? ''));
        $status = strtolower(trim((string) ($payment['transactionCompletionStatus'] ?? 'completed')));

        if ($receipt === '' || $status !== 'completed') {
            return null;
        }

        $existing = SchoolPayTransaction::where('school_id', $school->getKey())->where('receipt_number', $receipt)->first();

        if ($existing) {
            return $existing;
        }

        $type = $type === SchoolPayTransaction::TYPE_OTHER_FEES ? SchoolPayTransaction::TYPE_OTHER_FEES : SchoolPayTransaction::TYPE_SCHOOL_FEES;

        try {
            return DB::transaction(function () use ($school, $payment, $type, $source, $receipt): SchoolPayTransaction {
                $transaction = SchoolPayTransaction::create([
                    'school_id' => $school->getKey(),
                    'receipt_number' => $receipt,
                    'type' => $type,
                    'status' => $type === SchoolPayTransaction::TYPE_OTHER_FEES ? 'other_fees' : 'unmatched',
                    'source' => $source,
                    'amount' => (float) ($payment['amount'] ?? 0),
                    'paid_at' => $this->date($payment['paymentDateAndTime'] ?? null),
                    'student_payment_code' => $this->text($payment['studentPaymentCode'] ?? null, 40),
                    'student_registration_number' => $this->text($payment['studentRegistrationNumber'] ?? null, 60),
                    'student_name' => $this->text($payment['studentName'] ?? null, 255),
                    'channel' => $this->text($payment['sourcePaymentChannel'] ?? null, 255),
                    'channel_transaction_id' => $this->text($payment['sourceChannelTransactionId'] ?? null, 100),
                    'fee_description' => $this->text($payment['supplementaryFeeDescription'] ?? null, 255),
                    'payload' => $payment,
                ]);

                if ($type === SchoolPayTransaction::TYPE_SCHOOL_FEES && ($student = $this->findStudent($school, $transaction))) {
                    $this->record($transaction, $student);
                }

                return $transaction;
            });
        } catch (UniqueConstraintViolationException) {
            // The web hook and the nightly check reported it at the same moment.
            return SchoolPayTransaction::where('school_id', $school->getKey())->where('receipt_number', $receipt)->first();
        }
    }

    /**
     * The bursar picks the learner for a payment SchoolPay could not match.
     * Optionally keeps the SchoolPay code on the learner, so the next
     * payment matches by itself.
     */
    public function assign(SchoolPayTransaction $transaction, Student $student, bool $rememberCode = true): StudentPayment
    {
        if ($transaction->status === 'recorded') {
            throw new RuntimeException('This SchoolPay payment is already recorded.');
        }

        if ($student->school_id !== $transaction->school_id) {
            throw new RuntimeException('That learner is not in this school.');
        }

        return DB::transaction(function () use ($transaction, $student, $rememberCode): StudentPayment {
            if ($rememberCode && blank($student->schoolpay_code) && filled($transaction->student_payment_code)) {
                $student->forceFill(['schoolpay_code' => $transaction->student_payment_code])->save();
            }

            return $this->record($transaction, $student);
        });
    }

    /**
     * Ask SchoolPay for the school's payments over the last few days and
     * take any not seen yet. Notes the time and any error on the school.
     *
     * @return array{recorded: int, unmatched: int, other_fees: int, already: int}
     *
     * @throws RuntimeException when SchoolPay cannot be reached or refuses
     */
    public function sync(School $school, int $days = 3): array
    {
        if (! $school->usesSchoolPay()) {
            throw new RuntimeException('Turn on SchoolPay and enter the school code and API password first (School profile).');
        }

        $counts = ['recorded' => 0, 'unmatched' => 0, 'other_fees' => 0, 'already' => 0];
        $to = CarbonImmutable::now('Africa/Kampala')->startOfDay();
        $from = $to->subDays(max(1, $days) - 1);

        try {
            for ($start = $from; $start->lte($to); $start = $start->addDays(SchoolPayClient::MAX_DAYS)) {
                $end = $start->addDays(SchoolPayClient::MAX_DAYS - 1)->min($to);

                foreach ($this->client->transactions((string) $school->schoolpay_school_code, (string) $school->schoolpay_api_password, $start, $end) as $type => $payments) {
                    foreach ($payments as $payment) {
                        $isNew = ! SchoolPayTransaction::where('school_id', $school->getKey())
                            ->where('receipt_number', trim((string) ($payment['schoolpayReceiptNumber'] ?? '')))
                            ->exists();

                        $transaction = $this->ingest($school, (array) $payment, $type, 'sync');

                        if (! $transaction) {
                            continue;
                        }

                        $key = match (true) {
                            ! $isNew => 'already',
                            $transaction->status === 'recorded' => 'recorded',
                            $transaction->status === 'unmatched' => 'unmatched',
                            default => 'other_fees',
                        };

                        $counts[$key] += 1;
                    }
                }
            }
        } catch (Throwable $e) {
            $school->forceFill(['schoolpay_sync_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        $school->forceFill(['schoolpay_synced_at' => now(), 'schoolpay_sync_error' => null])->saveQuietly();

        return $counts;
    }

    protected function findStudent(School $school, SchoolPayTransaction $transaction): ?Student
    {
        if (filled($transaction->student_payment_code)) {
            $student = Student::where('school_id', $school->getKey())->where('schoolpay_code', $transaction->student_payment_code)->first();

            if ($student) {
                return $student;
            }
        }

        if (filled($transaction->student_registration_number)) {
            $matches = Student::where('school_id', $school->getKey())->where('admission_no', $transaction->student_registration_number)->limit(2)->get();

            return $matches->count() === 1 ? $matches->first() : null;
        }

        return null;
    }

    protected function record(SchoolPayTransaction $transaction, Student $student): StudentPayment
    {
        $payer = $this->text($transaction->payload['sourceChannelTransDetail'] ?? null, 255);

        $payment = StudentPayment::create([
            'school_id' => $transaction->school_id,
            'student_id' => $student->getKey(),
            'term_id' => Term::current($transaction->school_id)?->getKey(),
            'amount' => $transaction->amount,
            'paid_on' => ($transaction->paid_at ?? now())->toDateString(),
            'method' => 'schoolpay',
            'reference' => $transaction->receipt_number,
            'paid_by' => $payer,
            'recorded_by' => auth()->user()?->name ?? 'SchoolPay',
            'notes' => trim('SchoolPay'.($transaction->channel ? " via {$transaction->channel}" : '')
                .($transaction->channel_transaction_id ? ", transaction {$transaction->channel_transaction_id}" : '')),
        ]);

        $transaction->forceFill([
            'status' => 'recorded',
            'student_id' => $student->getKey(),
            'student_payment_id' => $payment->getKey(),
        ])->save();

        return $payment;
    }

    protected function date(mixed $value): ?CarbonInterface
    {
        try {
            return filled($value) ? CarbonImmutable::parse((string) $value, 'Africa/Kampala') : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
