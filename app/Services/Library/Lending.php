<?php

namespace App\Services\Library;

use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Models\Term;
use App\Services\BillingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lending library books: to one learner or member of staff, or a copy
 * each to a whole class (textbooks for the term); taking them back; and
 * declaring a copy lost, which for a learner bills its price to their
 * fees as a "Lost library book" charge.
 */
class Lending
{
    public function __construct(protected BillingService $billing) {}

    /** Lend a copy that is on the shelf. */
    public function issue(LibraryCopy $copy, ?Student $student, ?int $staffId, ?CarbonInterface $dueOn, ?string $notes = null): LibraryLoan
    {
        if ($copy->status !== LibraryCopy::AVAILABLE) {
            throw new InvalidArgumentException("{$copy->accession_no} is not on the shelf.");
        }

        if (! $student && ! $staffId) {
            throw new InvalidArgumentException('Choose who the book is issued to.');
        }

        return DB::transaction(function () use ($copy, $student, $staffId, $dueOn, $notes): LibraryLoan {
            $copy->update(['status' => LibraryCopy::ON_LOAN]);

            return LibraryLoan::create([
                'school_id' => $copy->school_id,
                'library_copy_id' => $copy->getKey(),
                'student_id' => $student?->getKey(),
                'staff_id' => $student ? null : $staffId,
                'term_id' => Term::current($copy->school_id)?->getKey(),
                'issued_on' => today(),
                'due_on' => $dueOn,
                'issued_by' => auth()->user()?->name,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * A copy of a title to each active learner in a class who has none
     * out yet, in name order, while copies last.
     *
     * @return array{issued: int, already: int, short: int}
     */
    public function issueToClass(LibraryBook $book, int $classId, ?int $sectionId, ?CarbonInterface $dueOn): array
    {
        $learners = Student::where('school_id', $book->school_id)
            ->where('status', 'active')
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->orderBy('name')
            ->get();

        $holding = LibraryLoan::outstanding()
            ->whereIn('library_copy_id', $book->copies()->pluck('id'))
            ->whereIn('student_id', $learners->modelKeys())
            ->pluck('student_id')
            ->all();

        $copies = $book->copies()->where('status', LibraryCopy::AVAILABLE)->orderBy('accession_no')->get();
        $result = ['issued' => 0, 'already' => count($holding), 'short' => 0];

        foreach ($learners as $learner) {
            if (in_array($learner->getKey(), $holding, true)) {
                continue;
            }

            $copy = $copies->shift();

            if (! $copy) {
                $result['short']++;

                continue;
            }

            $this->issue($copy, $learner, null, $dueOn);
            $result['issued']++;
        }

        return $result;
    }

    public function return(LibraryLoan $loan, ?string $condition = null, ?CarbonInterface $on = null): void
    {
        DB::transaction(function () use ($loan, $condition, $on): void {
            $loan->update([
                'returned_on' => $on ?? today(),
                'return_condition' => $condition,
                'received_by' => auth()->user()?->name,
            ]);

            $loan->copy->update([
                'status' => LibraryCopy::AVAILABLE,
                'condition' => $condition ?: $loan->copy->condition,
            ]);
        });
    }

    /**
     * The copy is not coming back. For a learner, the price (or the amount
     * given) goes on their fees for the current term; nothing is billed
     * for staff or when the amount is 0.
     */
    public function markLost(LibraryLoan $loan, ?float $bill = null): void
    {
        DB::transaction(function () use ($loan, $bill): void {
            $chargeId = null;
            $term = Term::current($loan->school_id);

            if ($loan->student && $term && $bill !== null && $bill > 0) {
                $chargeId = $this->billing->addCharge(
                    $loan->student,
                    $term,
                    'Lost library book: '.$loan->copy->book->title.' ('.$loan->copy->accession_no.')',
                    $bill,
                )->getKey();
            }

            $loan->update(['lost_on' => today(), 'student_charge_id' => $chargeId, 'received_by' => auth()->user()?->name]);
            $loan->copy->update(['status' => LibraryCopy::LOST]);
        });
    }
}
