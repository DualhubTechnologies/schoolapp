<?php

namespace App\Filament\App\Widgets;

use App\Filament\Pages\EnterMarks;
use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Services\Dashboard\TeacherLoad;
use App\Support\AcademicAccess;
use Filament\Widgets\Widget;

/**
 * One row per class and subject the teacher teaches, with how many marks
 * are in for the current exam and a button straight to that mark sheet.
 */
class TeacherMarksProgress extends Widget
{
    use SchoolScoped;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.teacher-marks-progress';

    protected static bool $isLazy = false;

    /** @return list<array{class: string, subject: string, exam: ?string, learners: int, entered: int, percent: ?int, url: string}> */
    public function rows(): array
    {
        $staffId = AcademicAccess::staffId();

        if (! $staffId) {
            return [];
        }

        return TeacherLoad::rows((int) $this->schoolId(), $staffId)
            ->map(fn ($r) => [
                'class' => $r['class']->name,
                'subject' => $r['subject']->name,
                'exam' => $r['assessment']?->name,
                'learners' => $r['learners'],
                'entered' => $r['entered'],
                'percent' => $r['assessment'] && $r['learners'] ? (int) min(100, round($r['entered'] / $r['learners'] * 100)) : null,
                'url' => EnterMarks::getUrl(array_filter([
                    'assessment' => $r['assessment']?->getKey(),
                    'class' => $r['class']->getKey(),
                    'subject' => $r['subject']->getKey(),
                ])),
            ])
            ->sortBy('percent')
            ->values()
            ->all();
    }
}
