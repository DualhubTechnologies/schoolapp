<?php

namespace App\Filament\Pages;

use App\Models\Combination;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Support\AcademicAccess;
use App\Support\Modules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Which optional subjects each learner takes, a whole class at a time:
 *
 *   O-Level / Primary  tick the electives each learner takes (Religious
 *                      Education choice, ICT, Agriculture, languages...)
 *   A-Level            each learner's combination, and their subsidiary
 *                      when it differs from the combination's
 *
 * Mark sheets then list only the learners who take a subject, and
 * A-Level points count the right principal subjects.
 */
class SubjectChoices extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Subject Choices';

    protected string $view = 'filament.pages.subject-choices';

    public ?int $classId = null;

    public ?int $sectionId = null;

    /** @var array<int, array<int, bool>> student id => subject id => takes it */
    public array $picks = [];

    /** @var array<int, ?int> student id => combination id (A-Level) */
    public array $combos = [];

    /** @var array<int, ?int> student id => subsidiary subject id; null = the combination's */
    public array $subs = [];

    public ?int $bulkCombination = null;

    /**
     * Academic staff set choices for any class; a class teacher for their
     * own stream.
     */
    public static function canAccess(): bool
    {
        return static::managesAll() || (AcademicAccess::teaches() && AcademicAccess::classTeacherStreams() !== []);
    }

    protected static function managesAll(): bool
    {
        return Modules::allows('academics') || AcademicAccess::manages();
    }

    public function mount(): void
    {
        $this->classId = request()->integer('class') ?: null;
        $this->pickOwnStream();
        $this->load();
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        $this->pickOwnStream();
        $this->load();
    }

    public function updatedSectionId(): void
    {
        $this->load();
    }

    protected function pickOwnStream(): void
    {
        if (! static::managesAll()) {
            $this->sectionId = AcademicAccess::classTeacherStreamsIn($this->classId)[0] ?? null;
        }
    }

    // ── Options ──

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()->school_id)
            ->when(! static::managesAll(), fn ($q) => $q->whereIn('id', array_values(AcademicAccess::classTeacherStreams())))
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId
            ? Section::where('school_class_id', $this->classId)
                ->when(! static::managesAll(), fn ($q) => $q->whereIn('id', AcademicAccess::classTeacherStreamsIn($this->classId)))
                ->orderBy('name')
                ->pluck('name', 'id')
            : collect();
    }

    public function canSeeWholeClass(): bool
    {
        return static::managesAll();
    }

    // ── Data ──

    #[Computed]
    public function schoolClass(): ?SchoolClass
    {
        return $this->classId
            ? SchoolClass::with(['classLevel', 'subjects'])->where('school_id', auth()->user()->school_id)->find($this->classId)
            : null;
    }

    public function isALevel(): bool
    {
        return $this->schoolClass?->curriculum() === 'a_level';
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function students(): Collection
    {
        $class = $this->schoolClass;

        if (! $class || (! static::managesAll() && ! in_array($this->sectionId, AcademicAccess::classTeacherStreamsIn($class->getKey()), true))) {
            return collect();
        }

        return Student::where('school_class_id', $class->getKey())
            ->where('status', 'active')
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            ->with(['section', 'electives'])
            ->orderBy('name')
            ->get();
    }

    /** Subjects everyone in the class takes. @return Collection<int, Subject> */
    public function compulsory(): Collection
    {
        return ($this->schoolClass?->subjects ?? collect())->filter(fn (Subject $s) => (bool) $s->pivot->is_compulsory)->values();
    }

    /** Subjects learners choose (the grid's columns). @return Collection<int, Subject> */
    #[Computed]
    public function optional(): Collection
    {
        return ($this->schoolClass?->subjects ?? collect())
            ->reject(fn (Subject $s) => (bool) $s->pivot->is_compulsory)
            ->values();
    }

    /** A-Level subsidiaries a learner may take instead of the combination's. @return Collection<int, Subject> */
    public function subsidiaryOptions(): Collection
    {
        return $this->optional->where('category', 'subsidiary')->values();
    }

    /** @return Collection<int, Combination> */
    #[Computed]
    public function combinations(): Collection
    {
        return Combination::where('school_id', auth()->user()->school_id)
            ->where('is_active', true)
            ->with(['subjects', 'subsidiary'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Combination subjects that are not set up for this class, so their
     * mark sheets could not be entered.
     *
     * @return array<string, list<string>> combination name => missing subject names
     */
    public function combinationGaps(): array
    {
        $classSubjects = ($this->schoolClass?->subjects ?? collect())->pluck('id');
        $used = collect($this->combos)->filter()->unique();

        return $this->combinations
            ->whereIn('id', $used)
            ->mapWithKeys(fn (Combination $c) => [$c->name => $c->subjects->merge(array_filter([$c->subsidiary]))
                ->reject(fn (Subject $s) => $classSubjects->contains($s->getKey()))
                ->map(fn (Subject $s) => $s->name)
                ->values()
                ->all()])
            ->filter()
            ->all();
    }

    /** @return array{min: int, max: int}|null */
    public function electiveRange(): ?array
    {
        return config('academics.electives.'.$this->schoolClass?->curriculum());
    }

    protected function load(): void
    {
        unset($this->schoolClass, $this->students, $this->optional, $this->combinations);
        $this->picks = $this->combos = $this->subs = [];
        $this->resetErrorBag();

        $optionalIds = $this->optional->pluck('id');

        foreach ($this->students as $student) {
            $chosen = $student->electives->pluck('id');
            $id = $student->getKey();

            if ($this->isALevel()) {
                $this->combos[$id] = $student->combination_id;
                $this->subs[$id] = $chosen->intersect($this->subsidiaryOptions()->pluck('id'))->first();
            } else {
                $this->picks[$id] = $optionalIds->mapWithKeys(fn ($sid) => [$sid => $chosen->contains($sid)])->all();
            }
        }
    }

    // ── Bulk helpers ──

    /** Tick or clear one subject for every learner shown. */
    public function setColumn(int $subjectId, bool $on): void
    {
        foreach (array_keys($this->picks) as $studentId) {
            $this->picks[$studentId][$subjectId] = $on;
        }
    }

    /** Give the chosen combination to learners who have none (or to all). */
    public function applyCombination(bool $onlyEmpty = true): void
    {
        if (! $this->bulkCombination) {
            return;
        }

        $n = 0;

        foreach ($this->combos as $studentId => $combo) {
            if (! $onlyEmpty || ! $combo) {
                $this->combos[$studentId] = $this->bulkCombination;
                $n++;
            }
        }

        Notification::make()->title("Combination set for {$n} ".str('learner')->plural($n))->body('Click Save to keep it.')->info()->send();
    }

    // ── Save ──

    public function save(): void
    {
        $students = $this->students;

        if ($students->isEmpty()) {
            return;
        }

        $optionalIds = $this->optional->pluck('id')->all();
        $comboIds = $this->combinations->pluck('id')->all();
        $subIds = $this->subsidiaryOptions()->pluck('id')->all();

        DB::transaction(function () use ($students, $optionalIds, $comboIds, $subIds) {
            foreach ($students as $student) {
                $id = $student->getKey();

                if ($this->isALevel()) {
                    $combo = in_array((int) ($this->combos[$id] ?? 0), $comboIds, true) ? (int) $this->combos[$id] : null;
                    $student->update(['combination_id' => $combo]);

                    // Keep only a subsidiary that differs from the combination's.
                    $sub = (int) ($this->subs[$id] ?? 0);
                    $comboSub = $this->combinations->firstWhere('id', $combo)?->subsidiary_subject_id;
                    $student->electives()->detach($subIds);

                    if ($sub && in_array($sub, $subIds, true) && $sub !== $comboSub) {
                        $student->electives()->attach($sub);
                    }

                    continue;
                }

                // Only this class's optional subjects are touched.
                $want = collect($this->picks[$id] ?? [])->filter()->keys()->map(fn ($s) => (int) $s)->intersect($optionalIds);
                $student->electives()->detach(array_diff($optionalIds, $want->all()));
                $student->electives()->syncWithoutDetaching($want->all());
            }
        });

        $this->load();
        Notification::make()->title('Subject choices saved')->body($students->count().' learners updated.')->success()->send();
    }
}
