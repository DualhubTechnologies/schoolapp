<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner's level in one syllabus topic, as NCDC describes it:
 *
 *   0  no learning outcome achieved
 *   1  some achieved, not enough for overall achievement
 *   2  most achieved, enough for overall achievement
 *   3  all achieved, with ease
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property int $student_id
 * @property int $subject_id
 * @property int $syllabus_topic_id
 * @property int $level
 * @property int|null $entered_by
 * @property-read SyllabusTopic $topic
 */
class TopicScore extends Model
{
    public const LEVELS = [
        3 => 'All outcomes achieved, with ease',
        2 => 'Most outcomes achieved, enough to achieve',
        1 => 'Some outcomes achieved, not enough',
        0 => 'No outcome achieved yet',
    ];

    /** A topic counts as achieved at this level or above. */
    public const ACHIEVED_FROM = 2;

    protected $fillable = [
        'school_id',
        'term_id',
        'student_id',
        'subject_id',
        'syllabus_topic_id',
        'level',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SyllabusTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(SyllabusTopic::class, 'syllabus_topic_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
