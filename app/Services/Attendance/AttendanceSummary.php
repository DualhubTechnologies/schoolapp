<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use Carbon\CarbonInterface;

/**
 * Attendance totals per learner over a period (a term, a month): days
 * the register was taken for them, and how many they were present (late
 * included), absent or excused.
 */
class AttendanceSummary
{
    /**
     * @param  array<array-key, int|string>  $studentIds
     * @return array<int|string, array{days: int, present: int, late: int, absent: int, excused: int, rate: float|null}>
     */
    public function forStudents(array $studentIds, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        if (! $studentIds) {
            return [];
        }

        $counts = AttendanceRecord::whereIn('student_id', $studentIds)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to->toDateString()))
            ->selectRaw('student_id, status, count(*) as total')
            ->groupBy('student_id', 'status')
            ->get();

        $summary = [];

        foreach ($studentIds as $id) {
            $row = $counts->where('student_id', $id)->pluck('total', 'status')->map(fn ($n) => (int) $n);
            $days = (int) $row->sum();
            $present = (int) ($row['present'] ?? 0) + (int) ($row['late'] ?? 0);

            $summary[$id] = [
                'days' => $days,
                'present' => $present,
                'late' => (int) ($row['late'] ?? 0),
                'absent' => (int) ($row['absent'] ?? 0),
                'excused' => (int) ($row['excused'] ?? 0),
                'rate' => $days ? round($present / $days * 100, 1) : null,
            ];
        }

        return $summary;
    }
}
