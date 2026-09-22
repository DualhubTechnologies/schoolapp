<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Term;
use App\Models\TermReport;
use App\Services\Academics\ResultsCalculator;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Write the term's report-card comments for a class, then print the
 * cards -- the whole class at once or one student.
 */
class ReportCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Report Cards';

    protected string $view = 'filament.pages.report-cards';

    public ?int $termId = null;

    public ?int $classId = null;

    public ?int $sectionId = null;

    public bool $showFees = true;

    /** @var array<int, array{class_teacher_comment: ?string, conduct: ?string}> */
    public array $comments = [];

    /** One head teacher's comment, printed on every report card of the class. */
    public ?string $headComment = null;

    public const CONDUCT = ['Excellent', 'Very good', 'Good', 'Fair', 'Needs improvement'];

    public function mount(): void
    {
        $this->termId = request()->integer('term') ?: Term::current()?->getKey();
        $this->classId = request()->integer('class') ?: null;
        $this->pickOwnStream();
        $this->loadComments();
    }

    public static function canAccess(): bool
    {
        return AcademicAccess::teaches();
    }

    public function updatedTermId(): void
    {
        $this->loadComments();
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        $this->pickOwnStream();
        $this->loadComments();
    }

    /** Teachers only work on the stream they are class teacher of. */
    protected function pickOwnStream(): void
    {
        if (! AcademicAccess::manages()) {
            $this->sectionId = AcademicAccess::classTeacherStreamsIn($this->classId)[0] ?? null;
        }
    }

    public function updatedSectionId(): void
    {
        $this->loadComments();
    }

    /** @return Collection<int, string> */
    public function termOptions(): Collection
    {
        return Term::where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->sortByDesc(fn (Term $t) => $t->sortKey())
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()]);
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)
            ->when(! AcademicAccess::manages(), fn ($q) => $q->whereIn('id', array_values(AcademicAccess::classTeacherStreams())))
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId
            ? Section::where('school_class_id', $this->classId)
                ->when(! AcademicAccess::manages(), fn ($q) => $q->whereIn('id', AcademicAccess::classTeacherStreamsIn($this->classId)))
                ->orderBy('name')
                ->pluck('name', 'id')
            : collect();
    }

    #[Computed]
    public function results(): ?array
    {
        $class = $this->classId ? SchoolClass::where('school_id', auth()->user()?->school_id)->find($this->classId) : null;
        $term = $this->termId ? Term::where('school_id', auth()->user()?->school_id)->find($this->termId) : null;

        // A teacher sees only the stream they are class teacher of.
        if (! AcademicAccess::manages() && ! in_array($this->sectionId, AcademicAccess::classTeacherStreamsIn($this->classId), true)) {
            return null;
        }

        return $class && $term ? app(ResultsCalculator::class)->forClass($class, $term, $this->sectionId) : null;
    }

    protected function loadComments(): void
    {
        unset($this->results);
        $this->comments = [];
        $this->headComment = collect($this->results['rows'] ?? [])
            ->pluck('report.head_teacher_comment')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        foreach ($this->results['rows'] ?? [] as $row) {
            $report = $row['report'];
            $this->comments[$row['student']->id] = [
                'class_teacher_comment' => $report?->class_teacher_comment,
                'conduct' => $report?->conduct,
            ];
        }
    }

    /**
     * Suggest a class-teacher comment for everyone who has none, from
     * their average. Nothing is saved until "Save comments".
     */
    public function fillComments(): void
    {
        $bands = collect(config('academics.comments'))->sortKeysDesc();
        $filled = 0;

        foreach ($this->results['rows'] ?? [] as $row) {
            $id = $row['student']->id;

            if ($row['average'] === null || filled($this->comments[$id]['class_teacher_comment'] ?? null)) {
                continue;
            }

            $this->comments[$id]['class_teacher_comment'] = $bands->first(fn ($text, $min) => $row['average'] >= $min);
            $filled++;
        }

        Notification::make()->title("{$filled} comments suggested")->body('Review them, then click Save comments.')->info()->send();
    }

    public function saveComments(): void
    {
        $studentIds = collect($this->results['rows'] ?? [])->pluck('student.id');
        $isHead = AcademicAccess::manages();
        $head = trim((string) $this->headComment) ?: null;

        foreach ($studentIds as $id) {
            $data = $this->comments[$id] ?? [];
            $values = [
                'class_teacher_comment' => trim((string) ($data['class_teacher_comment'] ?? '')) ?: null,
                'conduct' => $data['conduct'] ?? null,
            ];

            // Only the head teacher / administrator writes the head's comment.
            if ($isHead) {
                $values['head_teacher_comment'] = $head;
            }

            TermReport::updateOrCreate(['student_id' => $id, 'term_id' => $this->termId], $values);
        }

        unset($this->results);
        Notification::make()->title('Comments saved')->success()->send();
    }

    public function printUrl(?int $studentId = null): string
    {
        return route('filament.app.academics.report-cards', array_filter([
            'term' => $this->termId,
            'class' => $this->classId,
            'section' => $this->sectionId,
            'student' => $studentId,
            'fees' => $this->showFees ? 1 : 0,
            'print' => 1,
        ], fn ($v) => $v !== null));
    }
}
