<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    use Auditable;

    protected $fillable = [
        'school_id',
        'user_id',
        'name',
        'email',
        'nssf_number',
        'tin_number',
        'staff_no',
        'initials',
        'position',
        'department',
        'phone',
        'employment_date',
        'status',
        'gender',
        'nin',
        'employment_type',
        'category',
        'pays_nssf',
        'pays_lst',
    ];

    public const GENDERS = ['male' => 'Male', 'female' => 'Female'];

    public const CATEGORIES = [
        'teaching' => 'Teaching staff',
        'non_teaching' => 'Non-teaching staff',
    ];

    public function isTeaching(): bool
    {
        return $this->category === 'teaching';
    }

    public const EMPLOYMENT_TYPES = [
        'permanent' => 'Permanent',
        'contract' => 'Contract',
        'part_time' => 'Part-time',
        'volunteer' => 'Volunteer',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'on_leave' => 'On leave',
        'terminated' => 'Left the school',
    ];

    protected function casts(): array
    {
        return [
            'employment_date' => 'date',
            'pays_nssf' => 'boolean',
            'pays_lst' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ---- Payroll relationships ----

    public function salaries(): HasMany
    {
        return $this->hasMany(StaffSalary::class);
    }

    public function currentSalary(): HasOne
    {
        return $this->hasOne(StaffSalary::class)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from');
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(StaffAllowance::class);
    }

    public function activeAllowances(): HasMany
    {
        return $this->allowances()->where('is_active', true);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(StaffDeduction::class);
    }

    public function activeDeductions(): HasMany
    {
        return $this->deductions()->where('is_active', true);
    }

    public function bankDetails(): HasMany
    {
        return $this->hasMany(StaffBankDetail::class);
    }

    public function primaryBankDetail(): HasOne
    {
        return $this->hasOne(StaffBankDetail::class)->where('is_primary', true);
    }

    public function arrears(): HasMany
    {
        return $this->hasMany(SalaryArrear::class);
    }

    public function pendingArrears(): HasMany
    {
        return $this->arrears()->where('status', 'approved')->whereNull('applied_in_period_id');
    }

    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * Initials printed beside the teacher's subjects on report cards:
     * as typed on the staff record, else the first letter of each name.
     */
    public function reportInitials(): string
    {
        if (filled($this->initials)) {
            return $this->initials;
        }

        return collect(preg_split('/\s+/', trim((string) $this->name)))
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)).'.')
            ->implode('');
    }
}
