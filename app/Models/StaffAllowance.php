<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAllowance extends Model
{
    protected $fillable = [
        'staff_id',
        'allowance_type_id',
        'amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class);
    }
}
