<?php

namespace App\Services\Academics;

use App\Filament\Pages\EnterMarks;
use App\Models\Assessment;
use App\Models\Mark;
use App\Models\MarkSheet;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Support\AcademicAccess;
use Illuminate\Support\Collection;

/**
 * Mark sheets -- one exam, one class, one subject: who is on each sheet,
 * who may still change it, and how far every sheet of an exam has got.
 */
class MarkSheets
{
    /**
     * The students of a class who take a subject: everyone if it is
     * compulsory there; otherwise only those chosen for it ("Who takes this
     * subject" on Enter Marks), or whose A-Level combination includes it.
     * An elective nobody has been chosen for has nobody on its sheet.
     *
     * @param  Collection<int, Student>  $students  loaded with combination.subjects and electives
     * @param  Subject  $subject  loaded through the class, so with its class_subject pivot
     * @return array{students: Collection<int, Student>, filtered: bool}
     */
    public static function takers(Collection $students, Subject $subject): array
    {
        if ($subject->pivot->is_compulsory) {
            return ['students' => $students->values(), 'filtered' => false];
        }

        $takes = $students->filter(fn (Student $s) => $s->electives->contains('id', $subject->id)
            || $s->combination?->subjects->contains('id', $subject->id)
            || $s->combination?->subsidiary_subject_id === $subject->id);

        return ['students' => $takes->values(), 'filtered' => true];
    }

    /**
     * May the signed-in user still change the marks on this sheet? Nobody
     * once the exam is locked or the sheet approved; only those who manage
     * exams once it has been submitted.
     */
    public static function isEditable(Assessment $assessment, MarkSheet $sheet): bool
    {
        if ($assessment->isLocked() || $sheet->isApproved()) {
            return false;
        }

        return $sheet->isOpen() || AcademicAccess::manages();
    }

    /**
     * Every mark sheet of an exam across the school, for the Director of
     * Studies: the class, subject, teacher, how many learners have a mark
     * (or are marked absent) and where the sheet has got.
     *
     * @return Collection<int, MarkSheetProgress>
     */
    public function progress(Assessment $assessment, ?int $classId = null): Collection
    {
        $classes = SchoolClass::where('school_id', $assessment->school_id)
            ->when($classId, fn ($q) => $q->whereKey($classId))
            ->with(['classLevel', 'subjects'])
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->filter(fn (SchoolClass $c) => $assessment->appliesTo($c->curriculum()));

        $students = Student::whereIn('school_class_id', $classes->modelKeys())
            ->where('status', 'active')
            ->with(['combination.subjects', 'electives'])
            ->get()
            ->groupBy('school_class_id');

        // "subject:student" for every mark that counts as entered.
        $entered = Mark::where('assessment_id', $assessment->getKey())
            ->where(fn ($q) => $q->whereNotNull('score')->orWhere('is_absent', true))
            ->get(['subject_id', 'student_id'])
            ->map(fn (Mark $m) => "{$m->subject_id}:{$m->student_id}")
            ->flip();

        $sheets = MarkSheet::where('assessment_id', $assessment->getKey())
            ->get()
            ->keyBy(fn (MarkSheet $s) => "{$s->school_class_id}:{$s->subject_id}");

        $teachers = Staff::whereIn('id', $classes->flatMap(fn (SchoolClass $c) => $c->subjects->pluck('pivot.teacher_id'))->filter()->unique())
            ->get()
            ->mapWithKeys(fn (Staff $staff) => [$staff->id => (string) $staff->name]);

        $rows = [];

        foreach ($classes as $class) {
            foreach ($class->subjects as $subject) {
                $rows[] = $this->progressRow($assessment, $class, $subject, $students->get($class->getKey(), collect()), $entered, $sheets->get("{$class->getKey()}:{$subject->getKey()}"), $teachers[$subject->pivot->teacher_id] ?? null);
            }
        }

        return collect($rows)->values();
    }

    /**
     * @param  Collection<int, Student>  $students  the class's active students
     * @param  Collection<array-key, int>  $entered  "subject:student" keys of marks entered
     */
    protected function progressRow(Assessment $assessment, SchoolClass $class, Subject $subject, Collection $students, Collection $entered, ?MarkSheet $sheet, ?string $teacher): MarkSheetProgress
    {
        $roster = static::takers($students, $subject)['students'];
        $count = $roster->filter(fn (Student $s) => $entered->has("{$subject->id}:{$s->id}"))->count();
        $sheet ??= new MarkSheet(['status' => 'open', 'assessment_id' => $assessment->getKey(), 'school_class_id' => $class->getKey(), 'subject_id' => $subject->getKey()]);

        return new MarkSheetProgress(
            class: $class,
            subject: $subject,
            teacher: $teacher,
            learners: $roster->count(),
            entered: $count,
            sheet: $sheet,
            status: match (true) {
                ! $sheet->isOpen() => $sheet->status,
                $count === 0 => 'not_started',
                $count < $roster->count() => 'in_progress',
                default => 'complete',
            },
            url: EnterMarks::getUrl([
                'assessment' => $assessment->getKey(),
                'class' => $class->getKey(),
                'subject' => $subject->getKey(),
            ]),
        );
    }
}
