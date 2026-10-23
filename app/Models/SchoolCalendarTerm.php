<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One term of Uganda's national school calendar (all schools): when it
 * opens and closes. Paid SchoolHub terms follow these (TermCalendar).
 *
 * @property int $id
 * @property int $year
 * @property int $term
 * @property Carbon $opens_on
 * @property Carbon $closes_on
 * @property bool $is_estimate
 */
class SchoolCalendarTerm extends Model
{
    protected $fillable = ['year', 'term', 'opens_on', 'closes_on', 'is_estimate'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'term' => 'integer',
            'opens_on' => 'date',
            'closes_on' => 'date',
            'is_estimate' => 'boolean',
        ];
    }

    /** "Term 1, 2027" */
    public function label(): string
    {
        return "Term {$this->term}, {$this->year}";
    }
}
