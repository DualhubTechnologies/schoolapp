<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A school's student ID card template: which way up the card is, its two
 * colours, how long a card stays valid and the rules printed on the back.
 * One per school, edited on the ID Cards page and used by every preview,
 * print and export.
 *
 * @property int $id
 * @property int $school_id
 * @property string $orientation
 * @property string $primary_color
 * @property string $accent_color
 * @property string $validity
 * @property int $validity_months
 * @property string|null $back_notes
 */
class IdCardTemplate extends Model
{
    use Auditable;

    public const ORIENTATIONS = [
        'landscape' => 'Landscape',
        'portrait' => 'Portrait',
    ];

    public const VALIDITY = [
        'academic_year' => 'Until the end of the academic year',
        'months' => 'A number of months from printing',
    ];

    public const DEFAULTS = [
        'orientation' => 'landscape',
        'primary_color' => '#13294b',
        'accent_color' => '#c8a24a',
        'validity' => 'academic_year',
        'validity_months' => 12,
    ];

    /** Printed on the back when the school has not written its own rules. */
    public const DEFAULT_BACK_NOTES = "This card is not transferable and must be carried at all times while at school.\nIt remains the property of the school and must be returned on request.\nReport a lost card to the school office immediately.";

    protected $fillable = [
        'school_id',
        'orientation',
        'primary_color',
        'accent_color',
        'validity',
        'validity_months',
        'back_notes',
    ];

    protected function casts(): array
    {
        return [
            'validity_months' => 'integer',
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

    public function isPortrait(): bool
    {
        return $this->orientation === 'portrait';
    }

    /**
     * When a card printed on $issuedOn stops being valid: the end of the
     * current academic year (when it has an end date), or a number of
     * months from printing.
     */
    public function expiresOn(CarbonInterface $issuedOn, ?AcademicYear $year): CarbonInterface
    {
        if ($this->validity === 'academic_year' && $year?->end_date) {
            return Carbon::parse($year->end_date);
        }

        return $issuedOn->copy()->addMonths(max(1, $this->validity_months ?: 12));
    }

    /**
     * The rules on the back, one per line.
     *
     * @return list<string>
     */
    public function noteLines(): array
    {
        $notes = filled($this->back_notes) ? (string) $this->back_notes : self::DEFAULT_BACK_NOTES;

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $notes) ?: [])));
    }

    /**
     * Black or white, whichever reads better on the given colour, so any
     * colour the school picks keeps its text legible.
     */
    public static function textOn(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#ffffff';
        }

        [$r, $g, $b] = array_map(fn (string $pair): float => hexdec($pair) / 255, str_split($hex, 2));
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminance > 0.6 ? '#1b2433' : '#ffffff';
    }

    /** A colour as #rrggbb, or the fallback when it is not one. */
    public static function hex(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }
}
