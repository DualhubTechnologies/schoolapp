<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One learner on one day of the class register. Late counts as present
 * in attendance totals; excused absences are shown apart from absences.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int|null $school_class_id
 * @property Carbon $date
 * @property string $status
 * @property string|null $note
 * @property int|null $recorded_by
 * @property Carbon|null $parent_texted_at
 */
class AttendanceRecord extends Model
{
    public const STATUSES = [
        'present' => 'Present',
        'absent' => 'Absent',
        'late' => 'Late',
        'excused' => 'Excused',
    ];

    /** Short labels for the register buttons and printouts. */
    public const SHORT = [
        'present' => 'P',
        'absent' => 'A',
        'late' => 'L',
        'excused' => 'E',
    ];

    protected $fillable = [
        'school_id',
        'student_id',
        'school_class_id',
        'date',
        'status',
        'note',
        'recorded_by',
        'parent_texted_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'parent_texted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function countsAsPresent(): bool
    {
        return in_array($this->status, ['present', 'late'], true);
    }
}
