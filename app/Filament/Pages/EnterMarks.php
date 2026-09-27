<?php

namespace App\Filament\Pages;

use App\Models\Assessment;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Mark sheet: one exam, one class (or stream), one subject. Teachers see
 * only the class subjects assigned to them.
 */
class EnterMarks extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Enter Marks';

    protected string $view = 'filament.pages.enter-marks';

    public ?int $assessmentId = null;

    public ?int $classId = null;

    public ?int $sectionId = null;

    public ?int $subjectId = null;

    /** @var array<int, string|null> student id => score as typed */
    public array $scores = [];

    /** @var array<int, bool> */
    public array $absent = [];

    /** @var array<int, string|null> */
    public array $comments = [];

    public bool $showComments = false;

    /** When the sheet was last saved, shown beside the Save button. */
    public ?string $savedAt = null;

    public function mount(): void
    {
        $this->assessmentId = request()->integer('assessment') ?: $this->assessmentOptions()->keys()->first();

        // Deep links from the teacher dashboard; ignored unless the user may mark them.
        if (($class = request()->integer('class')) && $this->classOptions()->has($class)) {
            $this->classId = $class;

            if (($subject = request()->integer('subject')) && $this->subjectOptions()->has($subject)) {
                $this->subjectId = $subject;
                $this->updatedSubjectId();
            }
        }
    }

    public static function canAccess(): bool
    {
        return AcademicAccess::teaches();
    }

    // ── Choices ──

    public function updatedAssessmentId(): void
    {
        $this->classId = null;
        $this->resetSheet();
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        $this->subjectId = null;
        $this->resetSheet();
    }

    public function updatedSectionId(): void
    {
        $this->loadSheet();
    }

    public function updatedSubjectId(): void
    {
        $limit = $this->streamLimit();

        if ($limit !== null && ! in_array($this->sectionId, $limit, true)) {
            $this->sectionId = $limit[0] ?? null;
        }

        $this->loadSheet();
    }

    protected function resetSheet(): void
    {
        $this->subjectId = null;
        $this->scores = $this->absent = $this->comments = [];
    }

    /**
     * Exams in recent terms, current term first.
     *
     * @return Collection<int, string>
     */
    public function assessmentOptions(): Collection
    {
        $current = Term::current()?->getKey();

        return Assessment::where('school_id', auth()->user()?->school_id)
            ->with('term.academicYear')
            ->get()
            ->sortBy([
                fn ($a, $b) => ($b->term_id === $current) <=> ($a->term_id === $current),
                fn ($a, $b) => ($b->term?->sortKey() ?? '') <=> ($a->term?->sortKey() ?? ''),
                fn ($a, $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->mapWithKeys(fn (Assessment $a) => [$a->id => $a->name.' — '.($a->term?->label() ?? '').($a->isLocked() ? ' (locked)' : '')]);
    }

    #[Computed]
    public function assessment(): ?Assessment
    {
        return $this->assessmentId
            ? Assessment::where('school_id', auth()->user()?->school_id)->find($this->assessmentId)
            : null;
    }

    /**
     * Classes the exam covers that the user may mark.
     *
     * @return Collection<int, string>
     */
    public function classOptions(): Collection
    {
        $assessment = $this->assessment;

        if (! $assessment) {
            return collect();
        }

        return SchoolClass::where('school_id', auth()->user()?->school_id)
            ->with('classLevel')
            ->when(! AcademicAccess::manages(), fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('subjects', fn ($s) => $s->where('class_subject.teacher_id', AcademicAccess::staffId()))
                ->orWhereIn('id', array_values(AcademicAccess::classTeacherStreams()))))
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->filter(fn (SchoolClass $c) => $assessment->appliesTo($c->curriculum()))
            ->pluck('name', 'id');
    }

    #[Computed]
    public function schoolClass(): ?SchoolClass
    {
        return $this->classId ? SchoolClass::with(['classLevel', 'subjects'])->where('school_id', auth()->user()?->school_id)->find($this->classId) : null;
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        $limit = $this->streamLimit();

        return $this->classId
            ? Section::where('school_class_id', $this->classId)->when($limit !== null, fn ($q) => $q->whereIn('id', $limit))->orderBy('name')->pluck('name', 'id')
            : collect();
    }

    /**
     * Streams the user may mark for the chosen subject: null = all. A class
     * teacher entering a subject they do not teach gets only their stream.
     *
     * @return list<int>|null
     */
    public function streamLimit(): ?array
    {
        $subject = $this->subjectId ? $this->schoolClass?->subjects->firstWhere('id', $this->subjectId) : null;

        return $subject ? AcademicAccess::streamsForMarks($subject->pivot->teacher_id, $this->classId) : null;
    }

    /** @return Collection<int, string> */
    public function subjectOptions(): Collection
    {
        return ($this->schoolClass?->subjects ?? collect())
            ->filter(fn (Subject $s) => AcademicAccess::canEnterMarksFor($s->pivot->teacher_id, $this->classId))
            ->mapWithKeys(fn (Subject $s) => [$s->id => $s->name]);
    }

    #[Computed]
    public function subject(): ?Subject
    {
        $subject = $this->subjectId ? $this->schoolClass?->subjects->firstWhere('id', $this->subjectId) : null;

        // A teacher cannot open another teacher's mark sheet, even by
        // forcing the subject id.
        return $subject && AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId) ? $subject : null;
    }

    // ── The sheet ──

    /**
     * Students in the class (or stream) who take the subject: everyone if
     * it is compulsory there; otherwise those who chose it, or whose
     * A-Level combination includes it. If nobody's choices are recorded,
     * everyone is listed -- leave the score blank for those who do not
     * take it.
     *
     * @return array{students: Collection<int, Student>, filtered: bool}
     */
    public function sheetStudents(): array
    {
        $class = $this->schoolClass;
        $subject = $this->subject;

        if (! $class || ! $subject) {
            return ['students' => collect(), 'filtered' => false];
        }

        $students = Student::where('school_class_id', $class->getKey())
            ->where('status', 'active')
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            // A class teacher marking a subject they do not teach: their stream only.
            ->when(($limit = $this->streamLimit()) !== null, fn ($q) => $q->whereIn('section_id', $limit))
            ->with(['combination.subjects', 'electives'])
            ->orderBy('name')
            ->get();

        if ($subject->pivot->is_compulsory) {
            return ['students' => $students, 'filtered' => false];
        }

        $takes = $students->filter(fn (Student $s) => $s->electives->contains('id', $subject->id)
            || $s->combination?->subjects->contains('id', $subject->id)
            || $s->combination?->subsidiary_subject_id === $subject->id);

        return $takes->isEmpty()
            ? ['students' => $students, 'filtered' => false]
            : ['students' => $takes->values(), 'filtered' => true];
    }

    protected function loadSheet(): void
    {
        $this->scores = $this->absent = $this->comments = [];

        if (! $this->assessmentId || ! $this->subjectId) {
            return;
        }

        $ids = $this->sheetStudents()['students']->pluck('id');

        $marks = Mark::where('assessment_id', $this->assessmentId)
            ->where('subject_id', $this->subjectId)
            ->whereIn('student_id', $ids)
            ->get()
            ->keyBy('student_id');

        foreach ($ids as $id) {
            $mark = $marks->get($id);
            $this->scores[$id] = $mark?->score !== null ? rtrim(rtrim(number_format((float) $mark->score, 2, '.', ''), '0'), '.') : null;
            $this->absent[$id] = (bool) $mark?->is_absent;
            $this->comments[$id] = $mark?->comment;
        }

        $this->showComments = $marks->whereNotNull('comment')->isNotEmpty();
    }

    public function save(): void
    {
        $this->persist(quiet: false);
    }

    /**
     * Called by the sheet a few seconds after typing stops. Saves only when
     * every score is valid, and says so quietly beside the Save button.
     */
    public function autosave(): void
    {
        $this->persist(quiet: true);
    }

    protected function persist(bool $quiet): void
    {
        $assessment = $this->assessment;
        $subject = $this->subject;

        if (! $assessment || ! $subject) {
            return;
        }

        abort_unless(AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId), 403);

        if ($assessment->isLocked()) {
            if ($quiet) {
                return;
            }

            Notification::make()->title('This exam is locked')->body('Ask the administrator to reopen it.')->danger()->send();

            return;
        }

        $max = (float) $assessment->max_score;
        $ids = $this->sheetStudents()['students']->pluck('id');
        $errors = [];

        foreach ($ids as $id) {
            $raw = trim((string) ($this->scores[$id] ?? ''));

            if ($raw !== '' && (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max)) {
                $errors["scores.{$id}"] = 'Enter a number from 0 to '.(float) $max.'.';
            }
        }

        if ($errors) {
            $this->setErrorBag($errors);
            $this->savedAt = null;

            if ($quiet) {
                return;
            }

            Notification::make()->title(count($errors).' '.str('score')->plural(count($errors)).' need fixing')->body('Scores must be from 0 to '.(float) $max.'.')->danger()->send();

            return;
        }

        $this->resetErrorBag();
        $saved = 0;

        DB::transaction(function () use ($ids, &$saved) {
            foreach ($ids as $id) {
                $raw = trim((string) ($this->scores[$id] ?? ''));
                $absent = (bool) ($this->absent[$id] ?? false);
                $comment = trim((string) ($this->comments[$id] ?? '')) ?: null;
                $key = ['assessment_id' => $this->assessmentId, 'student_id' => $id, 'subject_id' => $this->subjectId];

                if ($raw === '' && ! $absent) {
                    Mark::where($key)->delete();

                    continue;
                }

                Mark::updateOrCreate($key, [
                    'score' => $absent ? null : (float) $raw,
                    'is_absent' => $absent,
                    'comment' => $comment,
                    'entered_by' => auth()->user()?->name,
                ]);
                $saved++;
            }
        });

        $this->savedAt = now()->format('g:i a');

        if (! $quiet) {
            Notification::make()->title("Marks saved ({$saved})")->success()->send();
        }
    }

    /**
     * Grade bands for live grading in the browser.
     *
     * @return list<array{grade: string, min: float}>
     */
    public function bandsForJs(): array
    {
        $class = $this->schoolClass;
        $subject = $this->subject;

        if (! $class || ! $subject) {
            return [];
        }

        $purpose = $class->curriculum() === 'a_level'
            ? ($subject->category === 'subsidiary' ? 'subsidiary' : 'principal')
            : 'subject';

        return GradingScale::where('school_id', $class->school_id)
            ->where('curriculum', $class->curriculum())
            ->where('purpose', $purpose)
            ->first()
            ?->bands
            ->map(fn ($b) => ['grade' => $b->grade, 'min' => (float) $b->min_score])
            ->values()
            ->all() ?? [];
    }
}
