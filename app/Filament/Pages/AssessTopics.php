<?php

namespace App\Filament\Pages;

use App\Filament\App\Resources\SyllabusTopics\SyllabusTopicResource;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\Term;
use App\Models\TopicScore;
use App\Services\Academics\MarkSheets;
use App\Services\Academics\TopicAssessment;
use App\Support\AcademicAccess;
use App\Support\Modules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * NCDC topic assessment (new lower-secondary curriculum): one class, one
 * subject, a level 0-3 for each learner in each syllabus topic. The
 * average level becomes the learner's mark in the term's Topic
 * assessment, the school-based share of the term result.
 *
 * @property-read SchoolClass|null $schoolClass
 * @property-read Subject|null $subject
 * @property-read EloquentCollection<int, SyllabusTopic> $topics
 */
class AssessTopics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Assess Topics';

    protected string $view = 'filament.pages.assess-topics';

    public ?int $classId = null;

    public ?int $subjectId = null;

    /** @var array<int, array<int, string|null>> student id => topic id => level as chosen */
    public array $levels = [];

    public static function canAccess(): bool
    {
        return Modules::allowsClass(static::class) && AcademicAccess::teaches();
    }

    public function term(): ?Term
    {
        return Term::current();
    }

    /**
     * O-Level classes the user may assess.
     *
     * @return Collection<int, string>
     */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)
            ->with('classLevel')
            ->when(! AcademicAccess::manages(), fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('subjects', fn ($s) => $s->where('class_subject.teacher_id', AcademicAccess::staffId()))
                ->orWhereIn('id', array_values(AcademicAccess::classTeacherStreams()))))
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->filter(fn (SchoolClass $class): bool => $class->curriculum() === 'o_level')
            ->pluck('name', 'id');
    }

    #[Computed]
    public function schoolClass(): ?SchoolClass
    {
        return $this->classId && $this->classOptions()->has($this->classId)
            ? SchoolClass::with(['classLevel', 'subjects'])->where('school_id', auth()->user()?->school_id)->whereKey($this->classId)->first()
            : null;
    }

    /** @return array<int, string> */
    public function subjectOptions(): array
    {
        $options = [];

        foreach ($this->schoolClass->subjects ?? [] as $subject) {
            if (AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId)) {
                $options[(int) $subject->id] = (string) $subject->name;
            }
        }

        return $options;
    }

    #[Computed]
    public function subject(): ?Subject
    {
        $subject = $this->subjectId ? $this->schoolClass?->subjects->firstWhere('id', $this->subjectId) : null;

        return $subject && AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId) ? $subject : null;
    }

    /** @return EloquentCollection<int, SyllabusTopic> */
    #[Computed]
    public function topics(): EloquentCollection
    {
        return app(TopicAssessment::class)->topicsFor($this->schoolClass, $this->subject);
    }

    /** @return Collection<int, Student> */
    public function students(): Collection
    {
        $class = $this->schoolClass;
        $subject = $this->subject;

        if (! $class || ! $subject) {
            return Student::query()->whereRaw('0 = 1')->get();
        }

        $students = Student::where('school_class_id', $class->getKey())
            ->where('status', 'active')
            ->when(($limit = AcademicAccess::streamsForMarks($subject->pivot->teacher_id, $class->getKey())) !== null, fn ($q) => $q->whereIn('section_id', $limit))
            ->with(['combination.subjects', 'electives'])
            ->orderBy('name')
            ->get();

        return MarkSheets::takers($students, $subject)['students'];
    }

    public function updatedClassId(): void
    {
        $this->subjectId = null;
        $this->levels = [];
        unset($this->schoolClass, $this->subject, $this->topics);
    }

    public function updatedSubjectId(): void
    {
        unset($this->subject, $this->topics);
        $this->load();
    }

    protected function load(): void
    {
        $this->levels = [];
        $studentIds = $this->studentIds();
        $topicIds = $this->topicIds();

        foreach ($studentIds as $studentId) {
            foreach ($topicIds as $topicId) {
                $this->levels[$studentId][$topicId] = null;
            }
        }

        foreach (TopicScore::whereIn('student_id', $studentIds)->whereIn('syllabus_topic_id', $topicIds)->get() as $score) {
            $this->levels[$score->student_id][$score->syllabus_topic_id] = (string) $score->level;
        }
    }

    /** @return array<int, int> */
    protected function studentIds(): array
    {
        return $this->students()->map(fn (Student $student): int => $student->id)->values()->all();
    }

    /** @return array<int, int> */
    protected function topicIds(): array
    {
        return $this->topics->map(fn (SyllabusTopic $topic): int => $topic->id)->values()->all();
    }

    public function save(TopicAssessment $assessment): void
    {
        $term = $this->term();
        $subject = $this->subject;

        if (! $term || ! $subject) {
            Notification::make()->title($term ? 'Choose a class and subject' : 'Set the current term first')->danger()->send();

            return;
        }

        abort_unless(AcademicAccess::canEnterMarksFor($subject->pivot->teacher_id, $this->classId), 403);

        $allowed = $this->studentIds();
        $levels = array_filter($this->levels, fn (int $studentId): bool => in_array($studentId, $allowed, true), ARRAY_FILTER_USE_KEY);

        foreach ($levels as $byTopic) {
            foreach ($byTopic as $level) {
                if ($level !== null && $level !== '' && ! in_array((string) $level, ['0', '1', '2', '3'], true)) {
                    Notification::make()->title('Levels go from 0 to 3')->danger()->send();

                    return;
                }
            }
        }

        $saved = $assessment->record($term, $subject, $levels, $this->topicIds(), auth()->user()?->id);

        Notification::make()
            ->title('Topic levels saved')
            ->body("{$saved} ".str('level')->plural($saved).' recorded. Each learner\'s average now counts as their Topic assessment mark for '.$term->label().'.')
            ->success()
            ->send();
    }

    public function topicsUrl(): string
    {
        return SyllabusTopicResource::getUrl();
    }
}
