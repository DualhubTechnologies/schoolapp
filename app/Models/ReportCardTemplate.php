<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school's report card template: the design, colours, border, the
 * wording in the header and footer, and which parts of the card are
 * printed. One per school, edited on the Report Cards page and used for
 * every printed card and every card a parent opens.
 *
 * @property int $id
 * @property int $school_id
 * @property string $design
 * @property string $font
 * @property string $border
 * @property string $primary_color
 * @property string $accent_color
 * @property string|null $title
 * @property string|null $header_note
 * @property string|null $footer_text
 * @property bool $watermark
 * @property array<string, bool>|null $show
 */
class ReportCardTemplate extends Model
{
    use Auditable;

    public const DESIGNS = [
        'classic' => 'Classic',
        'modern' => 'Modern',
        'compact' => 'Compact',
    ];

    public const DESIGN_HINTS = [
        'classic' => 'White header with a double rule under the school name.',
        'modern' => 'School name on a coloured band; coloured table headings.',
        'compact' => 'Smaller type and spacing, for classes with many subjects.',
    ];

    public const FONTS = [
        'sans' => 'Plain (sans-serif)',
        'serif' => 'Traditional (serif)',
    ];

    public const BORDERS = [
        'none' => 'No border',
        'line' => 'Single line',
        'double' => 'Double line',
        'ornate' => 'Double line with accent',
    ];

    /**
     * The parts of the card a school can leave out, and whether each is
     * printed by default.
     */
    public const SECTIONS = [
        'photo' => ['Student photo', true],
        'identifiers' => ['LIN / combination', true],
        'assessment_columns' => ['Marks for each exam (BOT, MOT, EOT…)', true],
        'teacher_initials' => ['Subject teacher initials', true],
        'total_marks' => ['Total marks', true],
        'class_position' => ['Position in class', true],
        'stream_position' => ['Position in stream', true],
        'conduct' => ['Conduct', true],
        'promotion' => ['Promotion decision (last term of the year)', true],
        'next_term' => ['Next term begins', true],
        'fees' => ['Fees balance and next term\'s fees', true],
        'class_teacher_comment' => ['Class teacher\'s comment', true],
        'head_teacher_comment' => ['Head teacher\'s comment', true],
        'signatures' => ['Signature lines', true],
        'grading_key' => ['Grading key', true],
    ];

    public const DEFAULTS = [
        'design' => 'classic',
        'font' => 'sans',
        'border' => 'none',
        'primary_color' => '#1e3a5f',
        'accent_color' => '#c8a24a',
        'watermark' => false,
    ];

    protected $fillable = [
        'school_id',
        'design',
        'font',
        'border',
        'primary_color',
        'accent_color',
        'title',
        'header_note',
        'footer_text',
        'watermark',
        'show',
    ];

    protected function casts(): array
    {
        return [
            'watermark' => 'boolean',
            'show' => 'array',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The school's saved template, or the defaults if it has never saved one.
     */
    public static function forSchool(int $schoolId): self
    {
        return static::firstOrNew(['school_id' => $schoolId], self::DEFAULTS);
    }

    /** Whether a part of the card is printed: the school's choice, else the default. */
    public function shows(string $section): bool
    {
        return (bool) ($this->show[$section] ?? self::SECTIONS[$section][1] ?? true);
    }

    /**
     * The parts that are printed, as the list a checkbox list edits.
     *
     * @return list<string>
     */
    public function shownSections(): array
    {
        return array_values(array_filter(array_keys(self::SECTIONS), fn (string $key): bool => $this->shows($key)));
    }

    /**
     * Every part as on/off, from the ticked list a checkbox list returns.
     *
     * @param  array<int, string>  $ticked
     * @return array<string, bool>
     */
    public static function showFromTicked(array $ticked): array
    {
        return collect(array_keys(self::SECTIONS))->mapWithKeys(fn (string $key): array => [$key => in_array($key, $ticked, true)])->all();
    }

    /** The card's heading: the school's own, else the usual one for the curriculum. */
    public function titleFor(?string $curriculum): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        return [
            'primary' => "Pupil's Progress Report",
            'o_level' => "Learner's Achievement Report",
            'a_level' => "Student's Progress Report",
        ][$curriculum] ?? 'Progress Report';
    }

    /**
     * The colours and choices the printed card's CSS reads, each checked so
     * a bad saved value falls back to the default rather than breaking it.
     *
     * @return array{design: string, font: string, border: string, primary: string, accent: string, onPrimary: string}
     */
    public function style(): array
    {
        $primary = IdCardTemplate::hex($this->primary_color, self::DEFAULTS['primary_color']);

        return [
            'design' => array_key_exists($this->design, self::DESIGNS) ? $this->design : 'classic',
            'font' => array_key_exists($this->font, self::FONTS) ? $this->font : 'sans',
            'border' => array_key_exists($this->border, self::BORDERS) ? $this->border : 'none',
            'primary' => $primary,
            'accent' => IdCardTemplate::hex($this->accent_color, self::DEFAULTS['accent_color']),
            'onPrimary' => IdCardTemplate::textOn($primary),
        ];
    }
}
