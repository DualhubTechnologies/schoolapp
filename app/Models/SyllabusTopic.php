<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A topic in a subject's NCDC syllabus for one class (S.1-S.4), assessed
 * as a whole on the 0-3 scale (App\Models\TopicScore).
 *
 * @property int $id
 * @property int $school_id
 * @property int $subject_id
 * @property int $class_number
 * @property string|null $code
 * @property string $name
 * @property int $sort_order
 * @property-read Subject $subject
 */
class SyllabusTopic extends Model
{
    protected $fillable = [
        'school_id',
        'subject_id',
        'class_number',
        'code',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'class_number' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<TopicScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(TopicScore::class);
    }

    /** "T5: Acids and alkalis", or the name alone. */
    public function label(): string
    {
        return filled($this->code) ? "{$this->code}: {$this->name}" : $this->name;
    }

    /** The short heading for a grid or report card column. */
    public function shortLabel(): string
    {
        return filled($this->code) ? (string) $this->code : mb_strimwidth($this->name, 0, 10, '…');
    }
}
