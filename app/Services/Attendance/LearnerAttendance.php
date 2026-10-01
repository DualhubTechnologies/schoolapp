<?php

namespace App\Services\Attendance;

use App\Models\Student;

/**
 * One learner's attendance totals over a period, for the Attendance Report.
 */
final readonly class LearnerAttendance
{
    public function __construct(
        public Student $student,
        public int $days,
        public int $present,
        public int $late,
        public int $absent,
        public int $excused,
        public ?float $rate,
    ) {}
}
