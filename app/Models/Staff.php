<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'name',
        'email',
        'staff_no',
        'position',
        'department',
        'phone',
        'employment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'employment_date' => 'date',
        ];
    }

    /**
     * The school this staff member belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The user account linked to this staff member (if they have login access).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}