<?php

namespace App\Services\Dashboard;

use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Term;
use Carbon\CarbonInterface;

/**
 * The headline numbers the dashboards (and the attention bell) share, so
 * each figure is worked out one way everywhere. Voided payments are
 * already excluded by StudentPayment's global scope.
 */
class SchoolFigures
{
    /** @return array{0: float, 1: int} total owed across all terms, number of students owing */
    public static function outstanding(int $schoolId): array
    {
        return once(function () use ($schoolId) {
            $charged = StudentCharge::where('school_id', $schoolId)
                ->groupBy('student_id')
                ->selectRaw('student_id, SUM(amount - discount_amount) as total')
                ->pluck('total', 'student_id');

            $paid = StudentPayment::where('school_id', $schoolId)
                ->groupBy('student_id')
                ->selectRaw('student_id, SUM(amount) as total')
                ->pluck('total', 'student_id');

            $balances = $charged
                ->map(fn ($total, $studentId) => (float) $total - (float) ($paid[$studentId] ?? 0))
                ->filter(fn ($balance) => $balance > 0);

            return [(float) $balances->sum(), $balances->count()];
        });
    }

    /** @return array{billed: float, collected: float} for one term */
    public static function term(int $schoolId, ?Term $term): array
    {
        if (! $term) {
            return ['billed' => 0.0, 'collected' => 0.0];
        }

        return once(fn () => [
            'billed' => (float) StudentCharge::where('school_id', $schoolId)->where('term_id', $term->getKey())->sum(\DB::raw('amount - discount_amount')),
            'collected' => (float) StudentPayment::where('school_id', $schoolId)->where('term_id', $term->getKey())->sum('amount'),
        ]);
    }

    /** @return array{amount: float, count: int} payments received in a date range (inclusive) */
    public static function collected(int $schoolId, CarbonInterface $from, CarbonInterface $to): array
    {
        $q = StudentPayment::where('school_id', $schoolId)->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()]);

        return ['amount' => (float) (clone $q)->sum('amount'), 'count' => (clone $q)->count()];
    }

    /** @return array{active: int, new: int} active students and those admitted this term */
    public static function students(int $schoolId, ?Term $term): array
    {
        return once(function () use ($schoolId, $term) {
            $active = Student::where('school_id', $schoolId)->where('status', 'active');

            return [
                'active' => (clone $active)->count(),
                'new' => ($term?->start_date && $term?->end_date)
                    ? (clone $active)->whereBetween('admission_date', [$term->start_date, $term->end_date])->count()
                    : 0,
            ];
        });
    }

    /** @return array{active: int, teaching: int, non_teaching: int} */
    public static function staff(int $schoolId): array
    {
        return once(function () use ($schoolId) {
            $byCategory = Staff::where('school_id', $schoolId)
                ->where('status', 'active')
                ->selectRaw('category, COUNT(*) as n')
                ->groupBy('category')
                ->pluck('n', 'category');

            return [
                'active' => (int) $byCategory->sum(),
                'teaching' => (int) ($byCategory['teaching'] ?? 0),
                'non_teaching' => (int) ($byCategory['non_teaching'] ?? 0),
            ];
        });
    }
}
