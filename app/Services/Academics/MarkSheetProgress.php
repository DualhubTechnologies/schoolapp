<?php

namespace App\Services\Academics;

use App\Models\MarkSheet;
use App\Models\SchoolClass;
use App\Models\Subject;

/**
 * Where one mark sheet of an exam has got, for the Marks Progress page.
 * $status is one of MarksProgress::STATUS_LABELS' keys: not_started,
 * in_progress, complete, submitted or approved.
 */
final readonly class MarkSheetProgress
{
    public function __construct(
        public SchoolClass $class,
        public Subject $subject,
        public ?string $teacher,
        public int $learners,
        public int $entered,
        public MarkSheet $sheet,
        public string $status,
        public string $url,
    ) {}

    public function percent(): int
    {
        return $this->learners ? (int) min(100, round($this->entered / $this->learners * 100)) : 0;
    }
}
