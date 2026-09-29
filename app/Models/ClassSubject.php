<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A subject as taught in one class: whether it is compulsory there and
 * which member of staff teaches it. Read through $subject->pivot when the
 * subject is loaded from a class.
 *
 * @property int $id
 * @property int $school_class_id
 * @property int $subject_id
 * @property bool $is_compulsory
 * @property int|null $teacher_id
 */
class ClassSubject extends Pivot
{
    protected $table = 'class_subject';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_compulsory' => 'boolean',
        ];
    }
}
