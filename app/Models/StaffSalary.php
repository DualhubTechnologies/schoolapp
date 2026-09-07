<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSalary extends Model
{
    protected $fillable = [
        'school_id',
        'staff_id',
        'base_salary',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the current active salary for a staff member.
     */
    public static function currentForStaff(int $staffId): ?self
    {
        return self::where('staff_id', $staffId)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();
    }
}
