<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The comments on a student's report card for one term.
 */
class TermReport extends Model
{
    protected $fillable = [
        'student_id',
        'term_id',
        'class_teacher_comment',
        'head_teacher_comment',
        'conduct',
        'days_present',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
