<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An income or expense head. "School fees" and "Salaries & wages" are
 * system categories: their figures come from fee receipts and payroll,
 * not from entries typed in.
 */
class FinanceCategory extends Model
{
    protected $fillable = [
        'school_id',
        'type',
        'name',
        'system_key',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Standard heads for a Ugandan school. system_key => filled automatically. */
    public const DEFAULTS = [
        'income' => [
            ['School fees', 'fees'],
            ['Government capitation grant (UPE/USE)', null],
            ['Donations & sponsorship', null],
            ['Uniform & requirements sales', null],
            ['Canteen & farm sales', null],
            ['Hire of facilities', null],
            ['Other income', null],
        ],
        'expense' => [
            ['Salaries & wages', 'payroll'],
            ['Teaching & learning materials', null],
            ['Examinations (UNEB, mocks, printing)', null],
            ['Feeding & meals', null],
            ['Utilities (power, water, internet)', null],
            ['Repairs & maintenance', null],
            ['Transport & fuel', null],
            ['Stationery & printing', null],
            ['Sports & co-curricular', null],
            ['Medical & sick bay', null],
            ['Administration & office', null],
            ['Staff welfare', null],
            ['Capital development (buildings, furniture)', null],
            ['Bank charges', null],
            ['Other expenses', null],
        ],
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(FinanceEntry::class);
    }

    public function isAutomatic(): bool
    {
        return $this->system_key !== null;
    }

    /**
     * Give a school the standard categories it does not have yet.
     */
    public static function ensureDefaults(int $schoolId): void
    {
        if (static::where('school_id', $schoolId)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $type => $rows) {
            foreach ($rows as $i => [$name, $key]) {
                static::create([
                    'school_id' => $schoolId,
                    'type' => $type,
                    'name' => $name,
                    'system_key' => $key,
                    'sort_order' => $i + 1,
                ]);
            }
        }
    }
}
