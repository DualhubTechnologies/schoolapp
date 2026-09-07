<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBankDetail extends Model
{
    protected $fillable = [
        'staff_id',
        'bank_name',
        'branch',
        'account_name',
        'account_number',
        'payment_method',
        'mobile_money_number',
        'mobile_money_provider',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
