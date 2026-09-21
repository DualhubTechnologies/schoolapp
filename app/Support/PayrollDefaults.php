<?php

namespace App\Support;

use App\Models\AllowanceType;
use App\Models\DeductionType;

/**
 * The allowance and deduction types most Ugandan schools use, so a new
 * school can start with one click instead of an empty list.
 */
class PayrollDefaults
{
    /** name => taxable. Most allowances are taxable employment income. */
    public const ALLOWANCES = [
        'Housing allowance' => true,
        'Transport allowance' => true,
        'Responsibility allowance' => true,
        'Duty allowance' => true,
        'Lunch allowance' => true,
    ];

    public const DEDUCTIONS = [
        'Salary advance',
        'Staff loan',
        'SACCO savings',
        'Staff welfare',
        'Union dues (UNATU)',
    ];

    /**
     * Add any of the standard types the school does not have yet.
     *
     * @return int how many were added
     */
    public static function allowances(int $schoolId): int
    {
        $added = 0;

        foreach (self::ALLOWANCES as $name => $taxable) {
            $type = AllowanceType::firstOrCreate(
                ['school_id' => $schoolId, 'name' => $name],
                ['is_taxable' => $taxable, 'is_active' => true],
            );
            $added += (int) $type->wasRecentlyCreated;
        }

        return $added;
    }

    public static function deductions(int $schoolId): int
    {
        $added = 0;

        foreach (self::DEDUCTIONS as $name) {
            $type = DeductionType::firstOrCreate(
                ['school_id' => $schoolId, 'name' => $name],
                ['is_statutory' => false, 'calculation_method' => 'fixed', 'is_active' => true],
            );
            $added += (int) $type->wasRecentlyCreated;
        }

        return $added;
    }
}
