<?php

namespace App\Filament\Pages;

use App\Filament\App\Resources\Staff\StaffResource;
use App\Filament\Support\IdCardsPage;
use App\Models\Staff;
use App\Models\Student;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Staff ID cards: every active staff member, narrowed by category or
 * department, one person (the row action on Staff) or a chosen set (the
 * bulk action there).
 */
class StaffIdCards extends IdCardsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Staff ID Cards';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Staff ID Cards';

    /** 'teaching', 'non_teaching' or '' for everyone. */
    public string $category = '';

    public string $department = '';

    public function holderType(): string
    {
        return 'staff';
    }

    public function updatedCategory(): void
    {
        $this->refreshCards();
    }

    public function updatedDepartment(): void
    {
        $this->refreshCards();
    }

    /** @return Collection<int, string> */
    public function departmentOptions(): Collection
    {
        return Staff::where('school_id', auth()->user()?->school_id)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department', 'department');
    }

    /** @return Collection<int, Staff> */
    protected function filteredHolders(): Collection
    {
        return Staff::where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->when($this->department !== '', fn ($q) => $q->where('department', $this->department))
            ->with('school')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Staff>
     */
    protected function holdersById(array $ids): Collection
    {
        return Staff::where('school_id', auth()->user()?->school_id)
            ->whereKey($ids)
            ->with('school')
            ->orderBy('name')
            ->get();
    }

    public function editUrl(Student|Staff $holder): string
    {
        return StaffResource::getUrl('edit', ['record' => $holder]);
    }

    public function filtersView(): string
    {
        return 'filament.pages.id-cards.staff-filters';
    }

    public function emptyHint(): string
    {
        return 'No active staff match. Add staff under Human Resources, or change the filters.';
    }
}
