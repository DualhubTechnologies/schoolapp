<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's move in a year-end promotion run.
 */
class Promotion extends Model
{
    protected $fillable = [
        'school_id',
        'batch',
        'academic_year_id',
        'student_id',
        'action',
        'recommendation',
        'reason',
        'average',
        'from_class_id',
        'from_section_id',
        'from_status',
        'from_combination_id',
        'to_class_id',
        'to_section_id',
        'performed_by',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return ['reversed_at' => 'datetime'];
    }

    public const ACTIONS = [
        'promote' => 'Promote',
        'probation' => 'Promote on probation',
        'repeat' => 'Repeat the class',
        'complete' => 'Completed (leaves the school)',
        'leave' => 'Left the school',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function toClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }
}
