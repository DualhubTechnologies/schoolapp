<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A library copy lent to a learner or a member of staff, until it comes
 * back, or is declared lost (and, for a learner, billed to their fees).
 *
 * @property int $id
 * @property int $school_id
 * @property int $library_copy_id
 * @property int|null $student_id
 * @property int|null $staff_id
 * @property int|null $term_id
 * @property Carbon $issued_on
 * @property Carbon|null $due_on
 * @property Carbon|null $returned_on
 * @property string|null $return_condition
 * @property Carbon|null $lost_on
 * @property int|null $student_charge_id
 * @property string|null $issued_by
 * @property string|null $received_by
 * @property string|null $notes
 */
class LibraryLoan extends Model
{
    use Auditable;

    public const STATES = [
        'out' => 'Borrowed',
        'overdue' => 'Overdue',
        'returned' => 'Returned',
        'lost' => 'Lost',
    ];

    protected $fillable = ['school_id', 'library_copy_id', 'student_id', 'staff_id', 'term_id', 'issued_on', 'due_on', 'returned_on', 'return_condition', 'lost_on', 'student_charge_id', 'issued_by', 'received_by', 'notes'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'returned_on' => 'date',
            'lost_on' => 'date',
        ];
    }

    /** @return BelongsTo<LibraryCopy, $this> */
    public function copy(): BelongsTo
    {
        return $this->belongsTo(LibraryCopy::class, 'library_copy_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<StudentCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(StudentCharge::class, 'student_charge_id');
    }

    /**
     * Books still out: neither returned nor declared lost.
     *
     * @param  Builder<LibraryLoan>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereNull('returned_on')->whereNull('lost_on');
    }

    /** out, overdue, returned or lost */
    public function state(): string
    {
        return match (true) {
            $this->lost_on !== null => 'lost',
            $this->returned_on !== null => 'returned',
            $this->due_on !== null && $this->due_on->lt(today()) => 'overdue',
            default => 'out',
        };
    }

    /** "Nakato Sarah (S.2 East)" or "Okello James (staff)" */
    public function borrowerName(): string
    {
        if ($this->student) {
            $class = $this->student->schoolClass?->name;

            return $this->student->name.($class ? " ({$class})" : '');
        }

        return ($this->staff->name ?? 'Unknown').' (staff)';
    }
}
