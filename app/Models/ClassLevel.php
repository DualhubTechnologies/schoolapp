<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O-Level and A-Level for a secondary school; Nursery and Primary for a
 * primary one.
 *
 * Seeded from the school's type when the school is created, so a new
 * school can start adding classes straight away — but editable, since a
 * school that words these differently should not be stuck with ours.
 *
 * No section heading: the school is one type or the other, so its type
 * already says which section these sit under.
 */
class ClassLevel extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'school_id',
        'name',
        'curriculum',
        'sort_order',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The levels a school starts with, by type. Defaults, not rules.
     */
    public const DEFAULTS = [
        School::TYPE_PRIMARY => [
            'Nursery',
            'Primary',
        ],
        School::TYPE_SECONDARY => [
            'O-Level',
            'A-Level',
        ],
    ];

    /** The curriculum each default level follows. */
    public const DEFAULT_CURRICULA = [
        'Nursery' => 'nursery',
        'Primary' => 'primary',
        'O-Level' => 'o_level',
        'A-Level' => 'a_level',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
