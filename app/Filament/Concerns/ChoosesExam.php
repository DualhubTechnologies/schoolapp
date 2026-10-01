<?php

namespace App\Filament\Concerns;

use App\Models\Assessment;
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
     * The chosen term's exams.
     *
     * @return Collection<int, string>
     */
    public function examOptions(): Collection
    {
        return $this->termId
            ? Assessment::where('school_id', auth()->user()?->school_id)
                ->where('term_id', $this->termId)
                ->orderBy('sort_order')
                ->orderBy('held_on')
                ->orderBy('id')
                ->pluck('name', 'id')
            : collect();
    }

    /**
     * The chosen exam, if it belongs to the chosen term.
     */
    protected function chosenExamId(): ?int
    {
        return $this->examId && $this->examOptions()->has($this->examId) ? $this->examId : null;
    }
}
