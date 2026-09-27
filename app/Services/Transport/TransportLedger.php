<?php

namespace App\Services\Transport;

use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;

/**
 * How much of a learner's payments went to the school van.
 *
 * Schools collect transport FIRST: every payment clears transport before
 * it counts towards school fees. The learner still has one bill and one
 * balance; this only decides the split, the same way on receipts, the
 * collections report and the route lists.
 *
 * The rule, per learner:
 *
 *   1. Payments are taken in term order (then date). A payment for a term
 *      covers transport charged for that term or earlier, oldest first,
 *      and only what is left goes to school fees.
 *   2. Money beyond what school fees need (a parent who paid ahead) also
 *      goes to any transport still unpaid, so a learner in credit never
 *      shows transport owing.
 *
 * Nothing is stored: the split is worked out from the charges and
 * payments, so a voided receipt or a corrected charge is reflected at once.
 */
class TransportLedger
{
    /** @var array<int, array{charged: float, paid: float, owed: float, terms: array<int, array{charged: float, paid: float}>, payments: array<int, float>}> */
    protected array $cache = [];

    /**
     * The learner's transport: charged, paid and owed overall; per term
     * (term id => charged and paid); and per payment (payment id => the
     * part of it that went to transport).
     *
     * @return array{charged: float, paid: float, owed: float, terms: array<int, array{charged: float, paid: float}>, payments: array<int, float>}
     */
    public function forStudent(Student $student): array
    {
        return $this->cache[$student->getKey()] ??= $this->work($student);
    }

    /**
     * The part of one payment that went to transport.
     */
    public function transportShare(StudentPayment $payment): float
    {
        $student = $payment->student;

        return $student ? ($this->forStudent($student)['payments'][$payment->getKey()] ?? 0.0) : 0.0;
    }

    /**
     * @return array{charged: float, paid: float, owed: float, terms: array<int, array{charged: float, paid: float}>, payments: array<int, float>}
     */
    protected function work(Student $student): array
    {
        $charges = StudentCharge::with('term.academicYear')->where('student_id', $student->getKey())->get();

        /** @var list<array{term_id: int, key: string, left: float}> $open transport still unpaid, oldest term first */
        $open = [];
        /** @var array<int, array{charged: float, paid: float}> $terms */
        $terms = [];
        $feesCharged = 0.0;

        foreach ($charges->sortBy(fn (StudentCharge $charge): string => $charge->term?->sortKey() ?? '') as $charge) {
            $net = max(0.0, (float) $charge->amount - (float) $charge->discount_amount);

            if ($charge->transport_route_id === null) {
                $feesCharged += $net;

                continue;
            }

            $terms[$charge->term_id] = ['charged' => ($terms[$charge->term_id]['charged'] ?? 0.0) + $net, 'paid' => 0.0];
            $open[] = ['term_id' => (int) $charge->term_id, 'key' => $charge->term?->sortKey() ?? '', 'left' => $net];
        }

        $payments = StudentPayment::with('term.academicYear')
            ->where('student_id', $student->getKey())
            ->get()
            ->sortBy(fn (StudentPayment $payment): string => ($payment->term?->sortKey() ?? '99999999-999')
                .'|'.$payment->paid_on
                .'|'.str_pad((string) $payment->getKey(), 10, '0', STR_PAD_LEFT));

        $shares = [];
        $totalPaid = 0.0;
        $transportPaid = 0.0;

        foreach ($payments as $payment) {
            $amount = (float) $payment->amount;
            $totalPaid += $amount;
            $upTo = $payment->term?->sortKey() ?? '99999999-999';
            $share = $this->cover($open, $terms, $amount, $upTo);

            $shares[$payment->getKey()] = $share;
            $transportPaid += $share;
        }

        // Anything paid beyond what school fees need clears transport too.
        $spare = ($totalPaid - $transportPaid) - $feesCharged;

        if ($spare > 0) {
            $transportPaid += $this->cover($open, $terms, $spare, null);
        }

        $charged = (float) array_sum(array_column($terms, 'charged'));

        return [
            'charged' => $charged,
            'paid' => $transportPaid,
            'owed' => max(0.0, $charged - $transportPaid),
            'terms' => $terms,
            'payments' => $shares,
        ];
    }

    /**
     * Spend $amount on unpaid transport, oldest first, up to the term with
     * sort key $upTo (null = any term). Returns how much was used.
     *
     * @param  list<array{term_id: int, key: string, left: float}>  $open
     * @param  array<int, array{charged: float, paid: float}>  $terms
     */
    protected function cover(array &$open, array &$terms, float $amount, ?string $upTo): float
    {
        $used = 0.0;

        foreach ($open as &$charge) {
            if ($amount - $used <= 0) {
                break;
            }

            if ($charge['left'] <= 0 || ($upTo !== null && $charge['key'] > $upTo)) {
                continue;
            }

            $take = min($charge['left'], $amount - $used);
            $charge['left'] -= $take;
            $term = $terms[$charge['term_id']] ?? ['charged' => 0.0, 'paid' => 0.0];
            $terms[$charge['term_id']] = ['charged' => $term['charged'], 'paid' => $term['paid'] + $take];
            $used += $take;
        }

        return $used;
    }
}
