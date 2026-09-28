<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\IdCardService;
use App\Support\Modules;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Preview and print student ID cards, front and back: one student, or a
 * whole class at once. Every card is checked against IdCardService first --
 * a card missing something it needs is flagged here, and Print / Export
 * PDF stay switched off until every card in the batch is complete, so a
 * school never finds out a card is blank on the back only after cutting it.
 *
 * @property Collection<int, Student> $students
 * @property Collection<int, array<string, mixed>> $cards
 */
class IdCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Students';

    protected static ?string $navigationLabel = 'ID Cards';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'ID Cards';

    protected string $view = 'filament.pages.id-cards';

    public ?int $classId = null;

    public ?int $sectionId = null;

    /** Set when opened for a single learner (the row action on Students). */
    public ?int $studentId = null;

    /** Set when opened for a chosen set of learners (the bulk action on Students). */
    public string $studentIds = '';

    public function mount(): void
    {
        $this->studentId = request()->integer('student') ?: null;
        $this->studentIds = (string) request()->query('students', '');
        $this->classId = request()->integer('class') ?: null;
        $this->sectionId = request()->integer('section') ?: null;
    }

    public static function canAccess(): bool
    {
        return Modules::allows('students');
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        unset($this->students, $this->cards);
    }

    public function updatedSectionId(): void
    {
        unset($this->students, $this->cards);
    }

    /** Clear the single-student / chosen-set filter to browse by class instead. */
    public function clearStudent(): void
    {
        $this->studentId = null;
        $this->studentIds = '';
        unset($this->students, $this->cards);
    }

    /** @return array<int, int> */
    protected function explicitStudentIds(): array
    {
        return collect(explode(',', $this->studentIds))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId
            ? Section::where('school_class_id', $this->classId)->orderBy('name')->pluck('name', 'id')
            : collect();
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function students(): Collection
    {
        $schoolId = auth()->user()?->school_id;

        if ($this->studentId) {
            return Student::where('school_id', $schoolId)->whereKey($this->studentId)->get();
        }

        if ($ids = $this->explicitStudentIds()) {
            return Student::where('school_id', $schoolId)
                ->whereKey($ids)
                ->with(['guardian', 'schoolClass', 'section'])
                ->orderBy('name')
                ->get();
        }

        if (! $this->classId) {
            return collect();
        }

        return Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('school_class_id', $this->classId)
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            ->with(['guardian', 'schoolClass', 'section'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Each student paired with its card data and readiness.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function cards(): Collection
    {
        return app(IdCardService::class)->cardsFor($this->students);
    }

    public function notReadyCount(): int
    {
        return $this->cards->where('ready', false)->count();
    }

    public function canPrint(): bool
    {
        return $this->cards->isNotEmpty() && $this->notReadyCount() === 0;
    }

    protected function studentIdsParam(): string
    {
        return $this->students->pluck('id')->implode(',');
    }

    public function printUrl(): string
    {
        return route('filament.app.students.id-cards.print', ['students' => $this->studentIdsParam(), 'print' => 1]);
    }

    public function exportUrl(): string
    {
        return route('filament.app.students.id-cards.export', ['students' => $this->studentIdsParam()]);
    }
}
