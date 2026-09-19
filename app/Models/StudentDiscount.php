<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDiscount extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'school_id',
        'student_id',
        'fee_structure_id',
        'term_id',
        'scope',
        'academic_year_id',
        'reason',
        'award_level',
        'type',
        'value',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public const TYPES = [
        'percentage' => 'Percentage (%)',
        'fixed' => 'Fixed amount',
    ];

    public const REASONS = [
        'scholarship' => 'Scholarship / bursary',
        'staff_child' => 'Staff child',
        'sibling' => 'Sibling discount',
        'hardship' => 'Hardship waiver',
        'other' => 'Other',
    ];

    /**
     * How long the award runs.
     *
     * An award that never ends is the dangerous default: it keeps
     * discounting long after it has lapsed, and nobody notices.
     */
    public const SCOPES = [
        'term' => 'This term only',
        'year' => 'This academic year',
        'ongoing' => 'Ongoing until cancelled',
    ];

    /**
     * Named award levels, as schools advertise them.
     *
     * Full covers everything — all fees, in full. Half is 50%. Partial
     * means the bursar set the value themselves.
     */
    public const AWARD_LEVELS = [
        'full' => 'Full bursary (100% of all fees)',
        'half' => 'Half bursary (50%)',
        'partial' => 'Partial — set the amount myself',
    ];

    // ── Relationships ──

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    // ── Does it apply? ──

    /**
     * Does this discount cover the given fee in the given term?
     *
     * No fee_structure_id means it covers the whole bill.
     * The scope decides how long it runs.
     */
    public function appliesTo(?int $feeStructureId, ?int $termId, ?Term $term = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // A full bursary covers everything, whatever fee it is.
        if ($this->fee_structure_id && $this->award_level !== 'full'
            && $this->fee_structure_id !== $feeStructureId) {
            return false;
        }

        return $this->coversTerm($termId, $term);
    }

    /**
     * Is the given term inside this award's period?
     */
    public function coversTerm(?int $termId, ?Term $term = null): bool
    {
        return match ($this->scope) {
            'term' => $this->term_id === null || $this->term_id === $termId,

            // Any term belonging to the award's academic year.
            'year' => $this->academic_year_id === null
                || ($term ?? Term::find($termId))?->academic_year_id === $this->academic_year_id,

            default => true,
        };
    }

    /**
     * Is this a fixed amount taken off the TOTAL bill rather than off a
     * single fee? Those are spent once across the whole bill.
     */
    public function isWholeBillPool(): bool
    {
        return $this->type === 'fixed' && $this->fee_structure_id === null;
    }

    /**
     * The value of this discount against a given charge. Never more than
     * the charge itself — a discount cannot turn a bill into a credit.
     */
    public function amountFor(float $charge): float
    {
        // A full bursary waives the charge entirely, regardless of the
        // value stored against it.
        if ($this->award_level === 'full') {
            return $charge;
        }

        $discount = $this->type === 'percentage'
            ? $charge * ((float) $this->value / 100)
            : (float) $this->value;

        return (float) min($discount, $charge);
    }

    // ── Display ──

    public function label(): string
    {
        if ($this->award_level === 'full') {
            return 'Full bursary';
        }

        $value = $this->type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '%'
            : number_format((float) $this->value, 0);

        $reason = self::REASONS[$this->reason] ?? $this->reason;

        return "{$reason} ({$value})";
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            'term' => $this->term?->label() ?? 'This term',
            'year' => $this->academicYear?->name ?? 'This year',
            default => 'Ongoing',
        };
    }

    /**
     * Has a year-scoped award run out? Used to flag awards that need
     * reviewing rather than leaving them to expire silently.
     */
    public function hasLapsed(): bool
    {
        if ($this->scope !== 'year' || ! $this->academic_year_id) {
            return false;
        }

        $current = AcademicYear::current($this->school_id);

        return $current !== null && $current->getKey() !== $this->academic_year_id;
    }
}
