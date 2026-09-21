<?php

namespace App\Models;

use App\Concerns\Auditable;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Day, Boarding, Half-boarder -- whatever the school actually runs.
 *
 * Deliberately a table rather than a hardcoded list: schools differ, and a
 * two-value enum would force every school into the same shape.
 */
class ResidencyType extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Badge colours, handed out in the order the school created its
     * residency types: the first (usually Day) is sky, the next violet...
     */
    public const BADGE_COLORS = [
        Color::Sky,
        Color::Violet,
        Color::Amber,
        Color::Emerald,
        Color::Rose,
        Color::Indigo,
        Color::Teal,
        Color::Orange,
    ];

    /** @var array<int, array<int, int>> school id => [residency type id => position] */
    protected static array $badgeOrder = [];

    /**
     * This type's badge colour. Each type in a school gets its own colour
     * (up to eight), and a type is always shown in the same colour on
     * every page.
     */
    public function badgeColor(): array
    {
        $order = static::$badgeOrder[$this->school_id] ??= static::query()
            ->where('school_id', $this->school_id)
            ->orderBy('id')
            ->pluck('id')
            ->flip()
            ->all();

        // A type created after the order was cached still gets a stable colour.
        $position = $order[$this->id] ?? crc32(mb_strtolower($this->name));

        return self::BADGE_COLORS[$position % count(self::BADGE_COLORS)];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}
