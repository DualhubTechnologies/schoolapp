<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The student's financial record.
 *
 * The ledger is DERIVED, never stored. It merges two things that already
 * exist — charges (invoice_items) and payments (student_payments) — into one
 * list in date order, with a running balance.
 *
 * Deriving it rather than storing it means it can never drift out of step
 * with reality: correct a charge or delete a payment and the ledger is
 * immediately right, with no reconciliation step and no second copy of the
 * truth to keep in line.
 *
 * Everything else is a view of this:
 *
 *   Ledger     every line, all terms — the bursar's working record
 *   Statement  a term's lines, summarised — what a parent receives
 *   Invoice    the current balance as a document — printed on demand
 */
class StudentLedger
{
    /**
     * Every transaction on the account, oldest first, with a running balance.
     *
     * Each entry is:
     *   date, type (charge|payment), description, reference,
     *   term, debit, credit, balance
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function entries(Student $student, ?Term $term = null): Collection
    {
        $charges = $this->charges($student);
        $payments = $this->payments($student);

        $entries = $charges->concat($payments)
            // Same-day ordering: charges before payments, so a payment
            // always appears to settle a charge rather than precede it.
            ->sortBy([
                fn ($a, $b) => $a['date'] <=> $b['date'],
                fn ($a, $b) => ($a['type'] === 'charge' ? 0 : 1) <=> ($b['type'] === 'charge' ? 0 : 1),
            ])
            ->values();

        // The running balance must be computed over the WHOLE history, even
        // when the caller only wants one term, or a term view would start
        // from zero and hide what came before.
        $balance = 0.0;

        $entries = $entries->map(function (array $entry) use (&$balance) {
            $balance += $entry['debit'] - $entry['credit'];
            $entry['balance'] = $balance;

            return $entry;
        });

        if ($term) {
            $entries = $entries->where('term_id', $term->getKey())->values();
        }

        return $entries;
    }

    /**
     * Charges — every invoice line, net of discount.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function charges(Student $student): Collection
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('terms', 'invoices.term_id', '=', 'terms.id')
            ->where('invoices.student_id', $student->getKey())
            ->select([
                'invoice_items.id',
                'invoice_items.description',
                'invoice_items.amount',
                'invoice_items.discount_amount',
                'invoice_items.discount_reason',
                'invoices.issue_date',
                'invoices.invoice_no',
                'invoices.term_id',
                'terms.name as term_name',
            ])
            ->get()
            ->map(fn ($row) => [
                'date' => $row->issue_date,
                'type' => 'charge',
                'description' => $row->description,
                'reference' => $row->invoice_no,
                'term_id' => $row->term_id,
                'term_name' => $row->term_name,
                'debit' => (float) $row->amount - (float) $row->discount_amount,
                'credit' => 0.0,
                'discount' => (float) $row->discount_amount,
                'discount_reason' => $row->discount_reason,
            ]);
    }

    /**
     * Payments — every receipt.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function payments(Student $student): Collection
    {
        return DB::table('student_payments')
            ->leftJoin('terms', 'student_payments.term_id', '=', 'terms.id')
            ->where('student_payments.student_id', $student->getKey())
            ->select([
                'student_payments.id',
                'student_payments.amount',
                'student_payments.paid_on',
                'student_payments.method',
                'student_payments.reference',
                'student_payments.term_id',
                'terms.name as term_name',
            ])
            ->get()
            ->map(fn ($row) => [
                'date' => $row->paid_on,
                'type' => 'payment',
                'description' => 'Payment received'
                    . (($label = \App\Models\StudentPayment::METHODS[$row->method] ?? null) ? " — {$label}" : ''),
                'reference' => $row->reference,
                'term_id' => $row->term_id,
                'term_name' => $row->term_name,
                'debit' => 0.0,
                'credit' => (float) $row->amount,
                'discount' => 0.0,
                'discount_reason' => null,
            ]);
    }

    /**
     * Totals for the whole account.
     *
     * @return array{charged: float, paid: float, balance: float, percent: float}
     */
    public function summary(Student $student): array
    {
        $charged = $student->totalCharged();
        $paid = $student->totalPaid();

        return [
            'charged' => $charged,
            'paid' => $paid,
            'balance' => $charged - $paid,
            'percent' => $student->percentagePaid(),
        ];
    }

    /**
     * Totals for one term, plus what was owed when the term opened.
     *
     * @return array{opening: float, charged: float, paid: float, closing: float}
     */
    public function termSummary(Student $student, Term $term): array
    {
        $all = $this->entries($student);
        $inTerm = $all->where('term_id', $term->getKey());

        $charged = (float) $inTerm->sum('debit');
        $paid = (float) $inTerm->sum('credit');

        // What the balance stood at immediately before this term's first
        // line — the "brought forward" figure on a statement.
        $firstIndex = $all->search(fn ($e) => $e['term_id'] === $term->getKey());

        $opening = $firstIndex === false || $firstIndex === 0
            ? 0.0
            : (float) $all[$firstIndex - 1]['balance'];

        return [
            'opening' => $opening,
            'charged' => $charged,
            'paid' => $paid,
            'closing' => $opening + $charged - $paid,
        ];
    }
}
