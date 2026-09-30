<?php

namespace App\Filament\Pages;

use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Support\IdCardsPage;
use App\Models\House;
use App\Models\ResidencyType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Student ID cards: a whole class or stream, one learner (the row action
 * on Students) or a chosen set (the bulk action there).
 */
class StudentIdCards extends IdCardsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Student ID Cards';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Student ID Cards';

    public ?int $classId = null;

    public ?int $sectionId = null;

    /** 'male', 'female' or '' for all. */
    public string $gender = '';

    public ?int $residencyId = null;

    public ?int $houseId = null;

    public function mount(): void
    {
        parent::mount();

        $this->classId = request()->integer('class') ?: null;
        $this->sectionId = request()->integer('section') ?: null;
    }

    public function holderType(): string
    {
        return 'students';
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        $this->refreshCards();
    }

    public function updatedSectionId(): void
    {
        $this->refreshCards();
    }

    public function updatedGender(): void
    {
        $this->refreshCards();
    }

    public function updatedResidencyId(): void
    {
        $this->refreshCards();
    }

    public function updatedHouseId(): void
    {
        $this->refreshCards();
    }

    /** Back to the class alone: no search, sex, residency, house or readiness. */
    public function clearFilters(): void
    {
        $this->gender = '';
        $this->residencyId = null;
        $this->houseId = null;
        $this->readiness = '';
        $this->clearSearch();
    }

    /** Whether anything beyond the class and stream narrows the list. */
    public function hasExtraFilters(): bool
    {
        return $this->search !== '' || $this->gender !== '' || $this->residencyId || $this->houseId || $this->readiness !== '';
    }

    /** @return Collection<int, string> */
    public function residencyOptions(): Collection
    {
        return ResidencyType::where('school_id', auth()->user()?->school_id)->orderBy('name')->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function houseOptions(): Collection
    {
        return House::where('school_id', auth()->user()?->school_id)->orderBy('name')->pluck('name', 'id');
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

    /**
     * A class (and stream), or a search across the whole school, narrowed
     * by sex, residency and house.
     *
     * @return Collection<int, Student>
     */
    protected function filteredHolders(): Collection
    {
        if (! $this->classId && $this->search === '') {
            return collect();
        }

        return $this->applySearchTo(Student::query(), ['students.name', 'students.admission_no', 'students.lin', 'students.schoolpay_code'])
            ->where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->when($this->classId, fn ($q) => $q->where('school_class_id', $this->classId))
            ->when($this->classId && $this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
            ->when($this->gender !== '', fn ($q) => $q->where('gender', $this->gender))
            ->when($this->residencyId, fn ($q) => $q->where('residency_type_id', $this->residencyId))
            ->when($this->houseId, fn ($q) => $q->where('house_id', $this->houseId))
            ->with(['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Student>
     */
    protected function holdersById(array $ids): Collection
    {
        return Student::where('school_id', auth()->user()?->school_id)
            ->whereKey($ids)
            ->with(['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType'])
            ->orderBy('name')
            ->get();
    }

    public function editUrl(Student|Staff $holder): string
    {
        return StudentResource::getUrl('edit', ['record' => $holder]);
    }

    public function filtersView(): string
    {
        return 'filament.pages.id-cards.student-filters';
    }

    public function emptyHint(): string
    {
        return 'Choose a class, or search for a learner by name or number, to see their ID cards.';
    }
}
