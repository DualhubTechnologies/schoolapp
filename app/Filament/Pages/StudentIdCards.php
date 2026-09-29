<?php

namespace App\Filament\Pages;

use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Support\IdCardsPage;
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
    protected function filteredHolders(): Collection
    {
        if (! $this->classId) {
            return collect();
        }

        return Student::where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->where('school_class_id', $this->classId)
            ->when($this->sectionId, fn ($q) => $q->where('section_id', $this->sectionId))
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
        return 'Choose a class to preview its ID cards.';
    }
}
