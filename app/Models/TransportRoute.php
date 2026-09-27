<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school van route with its fare per term, e.g. "Kakiri — 15,000".
 * Learners on a route are charged the fare each term; learners brought by
 * their parents have no route and pay nothing for transport.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $fare
 * @property string|null $one_way_fare
 * @property string|null $vehicle
 * @property string|null $driver_name
 * @property string|null $driver_phone
 * @property int|null $capacity
 * @property bool $is_active
 */
class TransportRoute extends Model
{
    use Auditable;

    /** How a learner uses the van. */
    public const TRIPS = [
        'both' => 'Both ways',
        'one_way' => 'One way only',
    ];

    protected $fillable = [
        'school_id',
        'name',
        'fare',
        'one_way_fare',
        'vehicle',
        'driver_name',
        'driver_phone',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fare' => 'decimal:2',
            'one_way_fare' => 'decimal:2',
            'capacity' => 'integer',
            'is_active' => 'boolean',
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
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * The termly fare for a trip. One way falls back to the full fare
     * when the route has no one-way price.
     */
    public function fareFor(?string $trip): float
    {
        return (float) ($trip === 'one_way' && $this->one_way_fare !== null ? $this->one_way_fare : $this->fare);
    }

    /**
     * How the charge reads on a bill, e.g. "Transport — Kakiri (one way)".
     */
    public function chargeDescription(?string $trip): string
    {
        return 'Transport — '.$this->name.($trip === 'one_way' ? ' (one way)' : '');
    }
}
