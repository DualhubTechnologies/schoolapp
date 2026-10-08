<?php

namespace App\Filament\Pages;

use App\Models\Assessment;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\MarkSheet;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Academics\MarkSheets;
use App\Services\Academics\ResultsCalculator;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mark sheet: one exam, one class (or stream), one subject. Teachers see
 * only the class subjects assigned to them, submit the finished sheet, and
 * the Director of Studies approves it (App\Models\MarkSheet). Sheets can
 * also be downloaded to Excel, filled in offline and uploaded again.
 *
 * @property-read Assessment|null $assessment
 * @property-read SchoolClass|null $schoolClass
 * @property-read Subject|null $subject
 * @property-read MarkSheet|null $markSheet
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

    /** Which paper of a subject sat as several papers (A-Level P1, P2...). */
    public int $paper = 1;

    /** @var array<int, string|null> student id => score as typed */
    public array $scores = [];

    /** @var array<int, bool> */
    public array $absent = [];

    /** @var array<int, string|null> */
    public array $comments = [];

    public bool $showComments = false;

    /** When the sheet was last saved, shown beside the Save button. */
    public ?string $savedAt = null;

    /**
     * Every exam of the chosen exam's term side by side (CA1, CA2, End of
     * Term...), entered on one sheet. The default; "This exam" shows one
     * exam with its hand-in, download and print tools.
     */
    public bool $allExams = true;

    /** @var array<int, array<int, string|null>> exam id => student id => score as typed, or "AB" */
    public array $grid = [];

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

    public function updatedPaper(): void
    {
        $this->paper = max(1, min($this->paper, $this->paperCount()));
        $this->loadSheet();
    }

    public function updatedAllExams(): void
    {
        $this->resetErrorBag();
        $this->loadSheet();
    }

    /** How many papers the chosen subject is sat as (1 for most). */
    public function paperCount(): int
    {
        return max(1, (int) ($this->subject->papers ?? 1));
    }

    public function updatedSubjectId(): void
    {
        $this->paper = 1;

        $limit = $this->streamLimit();

        if ($limit !== null && ! in_array($this->sectionId, $limit, true)) {
            $this->sectionId = $limit[0] ?? null;
        }

        $this->loadSheet();
    }

    protected function resetSheet(): void
    {
        unset($this->markSheet);
        $this->subjectId = null;
        $this->scores = $this->absent = $this->comments = $this->grid = [];
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
            // Topic assessment marks come from the Assess Topics page.
            ->where('type', '!=', 'topics')
            ->with('term.academicYear')
            ->get()
            ->sortBy([
                fn ($a, $b) => ($b->term_id === $current) <=> ($a->term_id === $current),
                fn ($a, $b) => ($b->term?->sortKey() ?? '') <=> ($a->term?->sortKey() ?? ''),
                fn ($a, $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->mapWithKeys(fn (Assessment $a) => [$a->id => $a->displayName().' — '.($a->term?->label() ?? '').($a->isLocked() ? ' (locked)' : '')]);
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
                ->orWhereIn('id', AcademicAccess::classTeacherClassIds())))
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->filter(fn (SchoolClass $c) => $assessment->covers($c))
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
            // One exam: only the subjects it is set in (a CA for Biology, say).
            ->filter(fn (Subject $s) => $this->allExams || ($this->assessment?->coversSubject($s->id) ?? true))
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

        return MarkSheets::takers($students, $subject);
    }

    protected function loadSheet(): void
    {
        unset($this->markSheet);
        $this->scores = $this->absent = $this->comments = $this->grid = [];

        if (! $this->assessmentId || ! $this->subjectId) {
            return;
        }

        if ($this->allExams) {
            $this->loadGrid();

            return;
        }

        $ids = $this->sheetStudents()['students']->pluck('id');

        $marks = Mark::where('assessment_id', $this->assessmentId)
            ->where('subject_id', $this->subjectId)
            ->where('paper', $this->paper)
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
        $this->allExams ? $this->persistGrid(quiet: false) : $this->persist(quiet: false);
    }

    /**
     * Called by the sheet a few seconds after typing stops. Saves only when
     * every score is valid, and says so quietly beside the Save button.
     */
    public function autosave(): void
    {
        $this->allExams ? $this->persistGrid(quiet: true) : $this->persist(quiet: true);
    }

    /**
     * Save the sheet. Returns false when nothing could be saved (closed
     * sheet or invalid scores).
     */
    protected function persist(bool $quiet): bool
    {
        $assessment = $this->assessment;
        $subject = $this->subject;

        if (! $assessment || ! $subject) {
            return false;
        }

        abort_unless(AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId), 403);

        if ($this->isReadOnly()) {
            if ($quiet) {
                return false;
            }

            Notification::make()->title($this->readOnlyReason() ?? 'This mark sheet is closed')->body('Ask the Director of Studies to reopen it.')->danger()->send();

            return false;
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
                return false;
            }

            Notification::make()->title(count($errors).' '.str('score')->plural(count($errors)).' need fixing')->body('Scores must be from 0 to '.(float) $max.'.')->danger()->send();

            return false;
        }

        $this->resetErrorBag();
        $saved = 0;

        DB::transaction(function () use ($ids, &$saved) {
            foreach ($ids as $id) {
                $raw = trim((string) ($this->scores[$id] ?? ''));
                $absent = (bool) ($this->absent[$id] ?? false);
                $comment = trim((string) ($this->comments[$id] ?? '')) ?: null;
                $key = ['assessment_id' => $this->assessmentId, 'student_id' => $id, 'subject_id' => $this->subjectId, 'paper' => $this->paper];

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

        return true;
    }

    // ── All exams of the term on one sheet ──

    /**
     * The chosen exam's term: every exam for the chosen class's curriculum
     * (CA, End of Term, project work...), in report card order. Topic
     * assessment marks come from Assess Topics, so it is left out.
     *
     * @return Collection<int, Assessment>
     */
    public function termAssessments(): Collection
    {
        $assessment = $this->assessment;
        $curriculum = $this->schoolClass?->curriculum();

        if (! $assessment) {
            return collect();
        }

        return Assessment::where('school_id', $assessment->school_id)
            ->where('term_id', $assessment->term_id)
            ->where('type', '!=', 'topics')
            // The report card's order (ResultsCalculator), so CA1, CA2… are the same exams on both.
            ->orderBy('sort_order')
            ->orderBy('held_on')
            ->orderBy('id')
            ->get()
            ->filter(fn (Assessment $a) => $a->appliesTo($curriculum)
                && $a->coversClass($this->classId)
                && (! $this->subjectId || $a->coversSubject($this->subjectId)))
            ->values();
    }

    /**
     * Column headings: CA1, CA2, EOT, or the exam's short name.
     *
     * @return array<int, string> exam id => heading
     */
    public function gridHeadings(): array
    {
        $exams = $this->termAssessments();
        $counts = $exams->countBy('type');
        $seen = [];
        $headings = [];

        foreach ($exams as $exam) {
            $seen[$exam->type] = ($seen[$exam->type] ?? 0) + 1;
            // Numbered when a term has several of a kind (CA1, CA2); a project shows its own name.
            $headings[$exam->id] = in_array($exam->type, ['bot', 'mot', 'eot', 'ca'], true)
                ? $exam->shortLabel().(($counts[$exam->type] ?? 0) > 1 ? $seen[$exam->type] : '')
                : mb_strimwidth($exam->name, 0, 14, '…');
        }

        return $headings;
    }

    /**
     * What the browser needs to work out each learner's term result as
     * marks are typed, the same way report cards do (ResultsCalculator):
     * each exam's "out of" and weight, whether it is averaged with others
     * of its kind (O-Level CAs) and whether it is school-based (CA).
     *
     * @return array{exams: array<int, array{id: int, max: float, weight: float, averaged: bool, schoolBased: bool}>, split: array{formative: int, summative: int}|null}
     */
    public function gridMaths(): array
    {
        $curriculum = $this->schoolClass?->curriculum();
        $exams = $this->termAssessments();
        $schoolBased = $exams->filter(fn (Assessment $a) => in_array($a->type, ResultsCalculator::SCHOOL_BASED_TYPES, true));
        $total = Assessment::totalWeight($exams, $curriculum);
        $formative = Assessment::totalWeight($schoolBased, $curriculum);
        $share = $total > 0 ? (int) round($formative / $total * 100) : 0;

        return [
            'exams' => $exams->map(fn (Assessment $a): array => [
                'id' => $a->id,
                'max' => (float) $a->max_score,
                'weight' => (float) $a->weight,
                'averaged' => $a->isAveragedIn($curriculum),
                'schoolBased' => in_array($a->type, ResultsCalculator::SCHOOL_BASED_TYPES, true),
            ])->values()->all(),
            'split' => $formative > 0 && $formative < $total ? ['formative' => $share, 'summative' => 100 - $share] : null,
        ];
    }

    /** Can this exam's column still be changed (not locked, approved, or submitted for a teacher)? */
    public function gridEditable(Assessment $exam): bool
    {
        return $this->classId && $this->subjectId
            && MarkSheets::isEditable($exam, MarkSheet::for($exam, $this->classId, (int) $this->subjectId));
    }

    protected function loadGrid(): void
    {
        $ids = $this->sheetStudents()['students']->pluck('id');
        $exams = $this->termAssessments();

        $marks = Mark::whereIn('assessment_id', $exams->pluck('id'))
            ->where('subject_id', $this->subjectId)
            ->where('paper', $this->paper)
            ->whereIn('student_id', $ids)
            ->get()
            ->groupBy('assessment_id');

        foreach ($exams as $exam) {
            $byStudent = ($marks->get($exam->id) ?? collect())->keyBy('student_id');

            foreach ($ids as $id) {
                $mark = $byStudent->get($id);
                $this->grid[$exam->id][$id] = match (true) {
                    (bool) $mark?->is_absent => 'AB',
                    $mark?->score !== null => rtrim(rtrim(number_format((float) $mark->score, 2, '.', ''), '0'), '.'),
                    default => null,
                };
            }
        }
    }

    /**
     * Save every editable column. A cell takes a score from 0 to the exam's
     * "out of", AB for absent, or blank for no mark.
     */
    protected function persistGrid(bool $quiet): bool
    {
        $subject = $this->subject;

        if (! $subject || ! $this->classId) {
            return false;
        }

        abort_unless(AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId), 403);

        $ids = $this->sheetStudents()['students']->pluck('id');
        $exams = $this->termAssessments()->filter(fn (Assessment $exam) => $this->gridEditable($exam));
        $errors = [];

        foreach ($exams as $exam) {
            $max = (float) $exam->max_score;

            foreach ($ids as $id) {
                $raw = trim((string) ($this->grid[$exam->id][$id] ?? ''));

                if ($raw !== '' && ! $this->isAbsentText($raw) && (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max)) {
                    $errors["grid.{$exam->id}.{$id}"] = 'Enter a number from 0 to '.$max.', or AB.';
                }
            }
        }

        if ($errors) {
            $this->setErrorBag($errors);
            $this->savedAt = null;

            if (! $quiet) {
                Notification::make()->title(count($errors).' '.str('mark')->plural(count($errors)).' need fixing')->body('Each mark must be from 0 to that exam\'s "out of", or AB for absent.')->danger()->send();
            }

            return false;
        }

        $this->resetErrorBag();
        $saved = 0;

        DB::transaction(function () use ($exams, $ids, &$saved) {
            foreach ($exams as $exam) {
                foreach ($ids as $id) {
                    $raw = trim((string) ($this->grid[$exam->id][$id] ?? ''));
                    $key = ['assessment_id' => $exam->id, 'student_id' => $id, 'subject_id' => $this->subjectId, 'paper' => $this->paper];

                    if ($raw === '') {
                        Mark::where($key)->delete();

                        continue;
                    }

                    $absent = $this->isAbsentText($raw);
                    $mark = Mark::firstOrNew($key);
                    $mark->fill(['score' => $absent ? null : (float) $raw, 'is_absent' => $absent, 'entered_by' => auth()->user()?->name])->save();
                    $saved++;
                }
            }
        });

        $this->savedAt = now()->format('g:i a');

        if (! $quiet) {
            Notification::make()->title("Marks saved ({$saved})")->success()->send();
        }

        return true;
    }

    /**
     * The mark sheet of each exam on the all-exams view, for this class
     * and subject.
     *
     * @return Collection<int, MarkSheet> exam id => sheet
     */
    public function gridSheets(): Collection
    {
        if (! $this->classId || ! $this->subjectId) {
            return collect();
        }

        return $this->termAssessments()->mapWithKeys(fn (Assessment $exam) => [$exam->id => MarkSheet::for($exam, (int) $this->classId, (int) $this->subjectId)]);
    }

    /**
     * Hand every open sheet of the term (this class and subject) to the
     * Director of Studies at once. Saves first.
     */
    public function submitAllAction(): Action
    {
        return Action::make('submitAll')
            ->label('Submit all for approval')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (): bool => $this->allExams && ! AcademicAccess::manages() && $this->openGridSheets()->isNotEmpty())
            ->requiresConfirmation()
            ->modalHeading('Submit these mark sheets?')
            ->modalDescription(fn (): string => 'Submits '.$this->openGridSheets()->count().' '.str('exam')->plural($this->openGridSheets()->count()).' ('.$this->openGridSheets()->map(fn (MarkSheet $sheet) => $sheet->assessment?->name)->implode(', ').'). You will not be able to change these marks after submitting, unless the Director of Studies returns a sheet.')
            ->modalSubmitActionLabel('Submit')
            ->action(function (): void {
                if (! $this->persistGrid(quiet: true)) {
                    Notification::make()->title('Fix the marks first')->body('Some marks are not valid.')->danger()->send();

                    return;
                }

                $sheets = $this->openGridSheets();
                $sheets->each(fn (MarkSheet $sheet) => $sheet->submit(auth()->user()));

                Notification::make()->title($sheets->count().' '.str('mark sheet')->plural($sheets->count()).' submitted')->body('The Director of Studies can now approve them.')->success()->send();
            });
    }

    /**
     * Approve every sheet of the term (this class and subject) that is not
     * yet approved or locked. Saves first.
     */
    public function approveAllAction(): Action
    {
        return Action::make('approveAll')
            ->label('Approve all')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => $this->allExams && AcademicAccess::manages() && $this->approvableGridSheets()->isNotEmpty())
            ->requiresConfirmation()
            ->modalHeading('Approve these mark sheets?')
            ->modalDescription(fn (): string => 'Approves '.$this->approvableGridSheets()->map(fn (MarkSheet $sheet) => $sheet->assessment?->name)->implode(', ').'. The marks become final: nobody can change them unless you reopen a sheet (under This exam).')
            ->modalSubmitActionLabel('Approve')
            ->action(function (): void {
                if (! $this->persistGrid(quiet: true)) {
                    Notification::make()->title('Fix the marks first')->body('Some marks are not valid.')->danger()->send();

                    return;
                }

                $sheets = $this->approvableGridSheets();
                $sheets->each(fn (MarkSheet $sheet) => $sheet->approve(auth()->user()));

                Notification::make()->title($sheets->count().' '.str('mark sheet')->plural($sheets->count()).' approved')->success()->send();
            });
    }

    /** @return Collection<int, MarkSheet> */
    protected function openGridSheets(): Collection
    {
        return $this->gridSheets()->filter(fn (MarkSheet $sheet) => $sheet->isOpen() && ! $sheet->assessment?->isLocked());
    }

    /** @return Collection<int, MarkSheet> */
    protected function approvableGridSheets(): Collection
    {
        return $this->gridSheets()->filter(fn (MarkSheet $sheet) => ! $sheet->isApproved() && ! $sheet->assessment?->isLocked());
    }

    protected function isAbsentText(string $raw): bool
    {
        return in_array(mb_strtolower($raw), ['ab', 'abs', 'absent'], true);
    }

    // ── Elective subjects: who takes them ──

    /**
     * Tick the learners in the class who take an elective subject. Only
     * they are listed on its mark sheet (and on report cards for it).
     */
    /**
     * A continuous assessment for just this class and subject, e.g. a
     * Biology activity given in S.2 only. It joins this term's mark sheet.
     */
    public function addCaAction(): Action
    {
        return Action::make('addCa')
            ->label('Add a CA')
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->visible(fn (): bool => $this->assessment !== null && $this->schoolClass !== null && $this->subject !== null && ! $this->assessment->isLocked())
            ->modalHeading(fn (): string => 'New continuous assessment for '.$this->subject?->name.', '.$this->schoolClass?->name)
            ->modalDescription('Only this class and subject get it. The other subjects and classes are not affected.')
            ->modalSubmitActionLabel('Add')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->default(fn (): string => 'CA '.($this->termAssessments()->where('type', 'ca')->count() + 1).' — '.$this->subject?->name),
                TextInput::make('max_score')
                    ->label('Marked out of')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->default(fn (): int => $this->schoolClass?->curriculum() === 'o_level' ? Assessment::O_LEVEL_CA_DEFAULT_MAX : 100)
                    ->helperText(fn (): string => $this->schoolClass?->curriculum() === 'o_level' ? 'Out of 20 by default, or 100. The report card shows it out of 3.' : 'Usually 100.'),
            ])
            ->action(function (array $data): void {
                $class = $this->schoolClass;
                $subject = $this->subject;
                $term = $this->assessment;

                if (! $class || ! $subject || ! $term) {
                    return;
                }

                $curriculum = $class->curriculum();
                // After the term's other assessments and before the End of
                // Term exam, on the sheet and the report card: End of Term
                // moves one place later if it shares their order number.
                $exams = $this->termAssessments();
                $endOfTerm = $exams->firstWhere('type', 'eot');
                $order = (int) $exams->where('type', '!=', 'eot')->max('sort_order');

                if ($endOfTerm && (int) $endOfTerm->sort_order <= $order) {
                    $endOfTerm->update(['sort_order' => $order + 1]);
                }

                Assessment::create([
                    'school_id' => $term->school_id,
                    'term_id' => $term->term_id,
                    'name' => $data['name'],
                    'type' => 'ca',
                    'curriculum' => $curriculum,
                    'class_ids' => [$class->getKey()],
                    'subject_ids' => [$subject->getKey()],
                    'max_score' => $data['max_score'],
                    'weight' => (float) config("academics.default_weights.{$curriculum}.ca", 0),
                    'sort_order' => $order,
                    // Dated today, so it follows the term's earlier CAs.
                    'held_on' => now()->toDateString(),
                ]);

                $this->allExams = true;
                $this->loadSheet();

                Notification::make()->title((string) $data['name'].' added for '.$subject->name.', '.$class->name)->success()->send();
            });
    }

    public function chooseLearnersAction(): Action
    {
        return Action::make('chooseLearners')
            ->label('Who takes this subject')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (): bool => $this->subject !== null && ! $this->subject->pivot->is_compulsory)
            ->modalHeading(fn (): string => 'Who takes '.$this->subject->name.'?')
            ->modalDescription('Tick the learners who take this elective. Only they appear on its mark sheet. Learners whose A-Level combination includes it are always listed.')
            ->fillForm(fn (): array => ['students' => $this->classLearners()
                ->filter(fn (Student $s) => $s->electives->contains('id', $this->subjectId))
                ->pluck('id')->all()])
            ->schema([
                CheckboxList::make('students')
                    ->hiddenLabel()
                    ->options(fn (): array => $this->classLearners()->mapWithKeys(fn (Student $s) => [$s->id => $s->name.' ('.$s->admission_no.')'])->all())
                    ->bulkToggleable()
                    ->searchable()
                    ->columns(2),
            ])
            ->modalSubmitActionLabel('Save')
            ->action(function (array $data): void {
                $subject = $this->subject;

                abort_unless($subject && AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId), 403);

                $chosen = array_map('intval', $data['students'] ?? []);

                foreach ($this->classLearners() as $student) {
                    in_array($student->id, $chosen, true)
                        ? $student->electives()->syncWithoutDetaching([$subject->id])
                        : $student->electives()->detach($subject->id);
                }

                $this->loadSheet();

                Notification::make()->title(count($chosen).' '.str('learner')->plural(count($chosen)).' take '.$subject->name)->success()->send();
            });
    }

    /**
     * Active learners in the chosen class (or stream) the user may mark.
     *
     * @return Collection<int, Student>
     */
    protected function classLearners(): Collection
    {
        return Student::where('school_class_id', $this->classId)
            ->where('status', 'active')
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            ->when(($limit = $this->streamLimit()) !== null, fn ($q) => $q->whereIn('section_id', $limit))
            ->with('electives')
            ->orderBy('name')
            ->get();
    }

    // ── Hand-in and approval ──

    #[Computed]
    public function markSheet(): ?MarkSheet
    {
        $assessment = $this->assessment;

        return $assessment && $this->classId && $this->subject
            ? MarkSheet::for($assessment, $this->classId, (int) $this->subjectId)
            : null;
    }

    public function isReadOnly(): bool
    {
        $assessment = $this->assessment;
        $sheet = $this->markSheet;

        return ! $assessment || ! $sheet || ! MarkSheets::isEditable($assessment, $sheet);
    }

    public function readOnlyReason(): ?string
    {
        return match (true) {
            (bool) $this->assessment?->isLocked() => 'This exam is locked',
            (bool) $this->markSheet?->isApproved() => 'This mark sheet has been approved',
            (bool) $this->markSheet?->isSubmitted() && ! AcademicAccess::manages() => 'This mark sheet has been submitted',
            default => null,
        };
    }

    /**
     * Students on the sheet with neither a score nor "absent".
     */
    public function missingCount(): int
    {
        return $this->sheetStudents()['students']
            ->filter(fn (Student $s) => trim((string) ($this->scores[$s->id] ?? '')) === '' && ! ($this->absent[$s->id] ?? false))
            ->count();
    }

    /**
     * The teacher hands the finished sheet to the Director of Studies.
     * Saves first; after this only those who manage exams can change it.
     */
    public function submitSheetAction(): Action
    {
        return Action::make('submitSheet')
            ->label('Submit for approval')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (): bool => (bool) $this->markSheet?->isOpen() && ! $this->isReadOnly())
            ->requiresConfirmation()
            ->modalHeading('Submit this mark sheet?')
            ->modalDescription(fn (): string => ($this->missingCount()
                ? $this->missingCount().' '.str('student')->plural($this->missingCount()).' still '.($this->missingCount() === 1 ? 'has' : 'have').' no mark. Leave them blank only if they do not take this subject. '
                : 'Every student has a mark. ')
                .(AcademicAccess::manages() ? '' : 'You will not be able to change the marks after submitting, unless the Director of Studies returns the sheet.'))
            ->modalSubmitActionLabel('Submit')
            ->action(function (): void {
                if (! $this->persist(quiet: true)) {
                    Notification::make()->title('Fix the marks first')->body('Some scores are not valid.')->danger()->send();

                    return;
                }

                $this->markSheet?->submit(auth()->user());
                unset($this->markSheet);

                Notification::make()->title('Mark sheet submitted')->body('The Director of Studies can now approve it.')->success()->send();
            });
    }

    public function approveSheetAction(): Action
    {
        return Action::make('approveSheet')
            ->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => AcademicAccess::manages() && ! $this->assessment?->isLocked() && ! $this->markSheet?->isApproved())
            ->requiresConfirmation()
            ->modalHeading('Approve this mark sheet?')
            ->modalDescription('The marks become final: nobody can change them unless you reopen the sheet.')
            ->action(function (): void {
                if (! $this->persist(quiet: true)) {
                    Notification::make()->title('Fix the marks first')->body('Some scores are not valid.')->danger()->send();

                    return;
                }

                $this->markSheet?->approve(auth()->user());
                unset($this->markSheet);

                Notification::make()->title('Mark sheet approved')->success()->send();
            });
    }

    /**
     * Send a submitted sheet back to the teacher, or reopen an approved
     * one, with a note of what to correct.
     */
    public function returnSheetAction(): Action
    {
        return Action::make('returnSheet')
            ->label(fn (): string => $this->markSheet?->isApproved() ? 'Reopen' : 'Return to teacher')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (): bool => AcademicAccess::manages() && ! $this->assessment?->isLocked() && ! $this->markSheet?->isOpen())
            ->modalHeading(fn (): string => $this->markSheet?->isApproved() ? 'Reopen this mark sheet?' : 'Return this mark sheet to the teacher?')
            ->modalDescription('The teacher can change the marks again and submit the sheet once more.')
            ->schema([
                Textarea::make('note')
                    ->label('What should be corrected?')
                    ->placeholder('e.g. Check the scores for the last five students')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $this->markSheet?->returnToTeacher($data['note'] ?? null);
                unset($this->markSheet);

                Notification::make()->title('Mark sheet reopened for the teacher')->success()->send();
            });
    }

    // ── Working offline: spreadsheet out, spreadsheet in ──

    /**
     * The sheet as a CSV file (opens in Excel): fill in the Score column,
     * or write AB for absent, and upload it again.
     */
    public function downloadSheet(): ?StreamedResponse
    {
        $assessment = $this->assessment;
        $subject = $this->subject;
        $class = $this->schoolClass;

        if (! $assessment || ! $subject || ! $class) {
            return null;
        }

        $students = $this->sheetStudents()['students'];
        $filename = str("{$class->name} {$subject->name} {$assessment->name}")->slug().'.csv';

        return response()->streamDownload(function () use ($students, $assessment) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Adm. No.', 'Name', 'Score (out of '.((float) $assessment->max_score).')', 'Comment']);

            foreach ($students as $student) {
                $id = $student->id;
                fputcsv($out, [
                    $student->admission_no,
                    $student->name,
                    ($this->absent[$id] ?? false) ? 'AB' : ($this->scores[$id] ?? ''),
                    $this->comments[$id] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function uploadSheetAction(): Action
    {
        return Action::make('uploadSheet')
            ->label('Upload from Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (): bool => ! $this->isReadOnly())
            ->modalHeading('Upload marks from a spreadsheet')
            ->modalDescription('Use "Download sheet", fill in the Score column in Excel (AB for absent), save it as CSV and upload it here. Students are matched by admission number.')
            ->schema([
                FileUpload::make('file')
                    ->label('CSV file')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                    ->maxSize(2048)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Upload and save')
            ->action(function (array $data): void {
                $file = $data['file'] ?? null;

                if (! $file instanceof TemporaryUploadedFile) {
                    return;
                }

                $this->importRows((string) file_get_contents($file->getRealPath()));
            });
    }

    /**
     * Put the scores from an uploaded CSV onto the sheet, then save it the
     * same way the Save button does (so the same checks apply).
     */
    public function importRows(string $csv): void
    {
        $byAdmission = $this->sheetStudents()['students']
            ->filter(fn (Student $s) => filled($s->admission_no))
            ->keyBy(fn (Student $s) => mb_strtolower(trim((string) $s->admission_no)));

        $lines = preg_split('/\r\n|\r|\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? ''));
        $matched = 0;
        $unknown = [];

        foreach (array_slice($lines ?: [], 1) as $line) {
            $cells = str_getcsv($line);
            $admission = mb_strtolower(trim((string) ($cells[0] ?? '')));

            if ($admission === '') {
                continue;
            }

            $student = $byAdmission->get($admission);

            if (! $student) {
                $unknown[] = trim((string) $cells[0]);

                continue;
            }

            $score = trim((string) ($cells[2] ?? ''));
            $isAbsent = in_array(mb_strtolower($score), ['ab', 'abs', 'absent'], true);

            $this->absent[$student->id] = $isAbsent;
            $this->scores[$student->id] = $isAbsent ? null : ($score === '' ? null : $score);

            if (array_key_exists(3, $cells)) {
                $this->comments[$student->id] = trim((string) $cells[3]) ?: null;
            }

            $matched++;
        }

        if ($matched === 0) {
            Notification::make()->title('No students matched')->body('Check that the first column holds the admission numbers, as in the downloaded sheet.')->danger()->send();

            return;
        }

        if ($this->persist(quiet: false) && $unknown) {
            Notification::make()
                ->title(count($unknown).' '.str('row')->plural(count($unknown)).' not on this sheet')
                ->body('Not found: '.implode(', ', array_slice($unknown, 0, 10)).(count($unknown) > 10 ? '…' : ''))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function printUrl(bool $blank = false): ?string
    {
        return $this->subject
            ? route('filament.app.academics.mark-sheet', array_filter([
                'assessment' => $this->assessmentId,
                'class' => $this->classId,
                'section' => $this->sectionId,
                'subject' => $this->subjectId,
                'blank' => $blank ? 1 : null,
            ]))
            : null;
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
