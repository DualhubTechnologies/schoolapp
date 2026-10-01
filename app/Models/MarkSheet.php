<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How far one mark sheet (exam + class + subject) has got:
 *
 *   open       the teacher is entering marks (no row yet means open too)
 *   submitted  the teacher has handed it in; only the Director of Studies
 *              (or a School Admin) may still change the marks
 *   approved   checked and final: nobody changes the marks until it is
 *              reopened
 *
 * @property int $id
 * @property int $school_id
 * @property int $assessment_id
 * @property int $school_class_id
 * @property int $subject_id
 * @property string $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $returned_note
 */
class MarkSheet extends Model
{
    use Auditable;

    public const STATUSES = [
        'open' => 'Being entered',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
    ];

    protected $fillable = [
        'school_id',
        'assessment_id',
        'school_class_id',
        'subject_id',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'returned_note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * The sheet's record, or a new open one (not saved) when nothing has
     * happened to it yet.
     */
    public static function for(Assessment $assessment, int $classId, int $subjectId): self
    {
        return static::firstOrNew(
            ['assessment_id' => $assessment->getKey(), 'school_class_id' => $classId, 'subject_id' => $subjectId],
            ['school_id' => $assessment->school_id, 'status' => 'open'],
        );
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function submit(User $by): void
    {
        $this->fill([
            'status' => 'submitted',
            'submitted_by' => $by->getKey(),
            'submitted_at' => now(),
            'returned_note' => null,
        ])->save();
    }

    public function approve(User $by): void
    {
        $this->fill([
            'status' => 'approved',
            'approved_by' => $by->getKey(),
            'approved_at' => now(),
            'returned_note' => null,
        ])->save();
    }

    /**
     * Back to the teacher to correct, with a note of what to fix.
     */
    public function returnToTeacher(?string $note): void
    {
        $this->fill([
            'status' => 'open',
            'approved_by' => null,
            'approved_at' => null,
            'returned_note' => $note ?: null,
        ])->save();
    }
}
