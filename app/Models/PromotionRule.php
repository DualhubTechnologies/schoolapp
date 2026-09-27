<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How a school decides who moves up at the end of the year, per
 * curriculum. The defaults follow common Ugandan practice and are meant
 * to be adjusted by each school.
 */
class PromotionRule extends Model
{
    protected $fillable = [
        'school_id',
        'curriculum',
        'basis',
        'min_average',
        'probation_margin',
        'required_subjects',
        'subject_pass_mark',
        'auto_promote_upto',
        'min_points',
    ];

    protected function casts(): array
    {
        return [
            'min_average' => 'decimal:2',
            'probation_margin' => 'decimal:2',
            'subject_pass_mark' => 'decimal:2',
            'required_subjects' => 'array',
        ];
    }

    public const BASES = [
        'annual' => 'Annual average (all terms of the year)',
        'final_term' => 'Final term only',
    ];

    /**
     * Starting rules per curriculum.
     *
     *   primary  P1–P3 progress automatically (thematic curriculum); from
     *            P4, a 40% annual average and passes in English and Maths
     *   o_level  a 40% annual average and English and Maths at least at
     *            "Basic" (30%) on the new curriculum scale
     *   a_level  S5 → S6 on a 35% average and at least one principal pass
     *            (2 points)
     */
    public const DEFAULTS = [
        'primary' => ['min_average' => 40, 'probation_margin' => 5, 'required_subjects' => ['English', 'Mathematics'], 'subject_pass_mark' => 40, 'auto_promote_upto' => 3, 'min_points' => null],
        'nursery' => ['min_average' => 0, 'probation_margin' => 0, 'required_subjects' => [], 'subject_pass_mark' => 0, 'auto_promote_upto' => 9, 'min_points' => null],
        'o_level' => ['min_average' => 40, 'probation_margin' => 5, 'required_subjects' => ['English', 'Mathematics'], 'subject_pass_mark' => 30, 'auto_promote_upto' => null, 'min_points' => null],
        'a_level' => ['min_average' => 35, 'probation_margin' => 5, 'required_subjects' => [], 'subject_pass_mark' => 35, 'auto_promote_upto' => null, 'min_points' => 2],
    ];

    public static function for(int $schoolId, ?string $curriculum): self
    {
        $curriculum ??= 'primary';

        return static::firstOrCreate(
            ['school_id' => $schoolId, 'curriculum' => $curriculum],
            ['basis' => 'annual'] + (self::DEFAULTS[$curriculum] ?? self::DEFAULTS['primary']),
        );
    }

    /** "Average 40%, pass English & Mathematics (40%)" */
    public function summary(): string
    {
        $parts = [];

        if ($this->auto_promote_upto) {
            $parts[] = "automatic up to class {$this->auto_promote_upto}";
        }
        if ((float) $this->min_average > 0) {
            $parts[] = (self::BASES[$this->basis] === self::BASES['annual'] ? 'annual' : 'final-term').' average '.(float) $this->min_average.'%';
        }
        if ($this->required_subjects) {
            $parts[] = 'pass '.implode(' & ', $this->required_subjects).' ('.(float) $this->subject_pass_mark.'%)';
        }
        if ($this->min_points) {
            $parts[] = "at least {$this->min_points} points";
        }
        if ((float) $this->probation_margin > 0) {
            $parts[] = 'probation within '.(float) $this->probation_margin.'% of the line';
        }

        return ucfirst(implode(', ', $parts)) ?: 'Everyone is promoted';
    }
}
