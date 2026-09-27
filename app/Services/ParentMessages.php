<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Term;

/**
 * The texts SchoolHub sends to parents, in the school's chosen language
 * (schools.parent_sms_language), each ending with the parent page link.
 * Kept short: one SMS is 160 characters.
 */
class ParentMessages
{
    public const LANGUAGES = [
        'en' => 'English',
        'lg' => 'Luganda',
    ];

    public function __construct(protected SmsSender $sms) {}

    public function receipt(StudentPayment $payment): string
    {
        $student = $payment->student;
        $school = $student->school;

        $values = [
            '{school}' => $school->name,
            '{amount}' => number_format((float) $payment->amount),
            '{student}' => $student->name,
            '{class}' => $student->schoolClass->name ?? '',
            '{receipt}' => $payment->receipt_no,
            '{balance}' => $this->balanceText($student, $school->parent_sms_language ?? 'en'),
            '{link}' => $student->parentPageUrl(),
        ];

        $template = match ($school->parent_sms_language ?? 'en') {
            'lg' => '{school}: Tufunye UGX {amount} ez\'ebisale bya {student} ({class}). Lisiiti {receipt}. {balance}. Laba: {link}',
            default => '{school}: Received UGX {amount} for {student} ({class}). Receipt {receipt}. {balance}. Details: {link}',
        };

        return strtr($template, $values);
    }

    /**
     * Text a receipt to the payer. Returns null when sent, or why not.
     */
    public function sendReceipt(StudentPayment $payment, string $phone): ?string
    {
        $result = $this->sms->send($phone, $this->receipt($payment));

        return $result['ok'] ? null : ($result['error'] ?? 'The SMS could not be sent.');
    }

    /**
     * A wa.me link that opens WhatsApp with the receipt text ready to send.
     */
    public function whatsAppReceiptUrl(StudentPayment $payment, ?string $phone): string
    {
        $to = ltrim((string) SmsSender::normalisePhone($phone), '+');

        return 'https://wa.me/'.$to.'?text='.rawurlencode($this->receipt($payment));
    }

    public function reportCardReady(Student $student, Term $term): string
    {
        $values = [
            '{school}' => $student->school->name,
            '{student}' => $student->name,
            '{term}' => $term->name,
            '{link}' => $student->parentPageUrl(),
        ];

        $template = match ($student->school->parent_sms_language ?? 'en') {
            'lg' => '{school}: Ripoota ya {student} eya {term} ewedde. Giraba wano: {link}',
            default => '{school}: {student}\'s {term} report card is ready. Open it here: {link}',
        };

        return strtr($template, $values);
    }

    /**
     * Text each family that their child's report card for the term is on
     * their parent page.
     *
     * @param  iterable<Student>  $students
     * @return array{sent: int, failed: int, no_phone: int}
     */
    public function sendReportCardsReady(iterable $students, Term $term): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'no_phone' => 0];
        $reminders = app(FeeReminderService::class);

        foreach ($students as $student) {
            $student->loadMissing(['guardian', 'school']);
            $phone = $reminders->phoneFor($student);

            if (! $phone) {
                $result['no_phone']++;

                continue;
            }

            $result[$this->sms->send($phone, $this->reportCardReady($student, $term))['ok'] ? 'sent' : 'failed']++;
        }

        return $result;
    }

    protected function balanceText(Student $student, string $language): string
    {
        $balance = $student->balance();

        return match ($language) {
            'lg' => $balance > 0 ? 'Ebisigaddeyo UGX '.number_format($balance) : 'Ebisale biweddeyo',
            default => $balance > 0 ? 'Balance UGX '.number_format($balance) : 'Fees fully paid',
        };
    }
}
