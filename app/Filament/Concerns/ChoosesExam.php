<?php

namespace App\Filament\Concerns;

use App\Models\Assessment;
use App\Models\SchoolClass;
use Illuminate\Support\Collection;

/**
 * "Results for": the whole term, or one of its exams on its own (a
 * mid-term report, say). Used by pages that show results for $termId.
 */
trait ChoosesExam
{
    /** null = the whole term (every exam, by weight). */
    public ?int $examId = null;

    /**
     * The chosen term's exams (those the chosen class sits, once one is chosen).
     *
     * @return Collection<int, string>
     */
    public function examOptions(): Collection
    {
        if (! $this->termId) {
            return collect();
        }

        // Only the exams the chosen class sits (no A-Level exams for S.1).
        $class = $this->classId ? SchoolClass::with('classLevel')->find($this->classId) : null;

        return Assessment::where('school_id', auth()->user()?->school_id)
            ->where('term_id', $this->termId)
            ->orderBy('sort_order')
            ->orderBy('held_on')
            ->orderBy('id')
            ->get()
            ->filter(fn (Assessment $a): bool => $class === null || $a->covers($class))
            ->mapWithKeys(fn (Assessment $a): array => [$a->id => $a->displayName()]);
    }

    /**
     * The chosen exam, if it belongs to the chosen term.
     */
    protected function chosenExamId(): ?int
    {
        return $this->examId && $this->examOptions()->has($this->examId) ? $this->examId : null;
    }
}
