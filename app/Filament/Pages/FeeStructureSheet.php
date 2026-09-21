<?php

namespace App\Filament\Pages;

use App\Models\ClassLevel;
use App\Models\FeeStructure;
use App\Models\ResidencyType;
use App\Models\Term;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The fees structure as a school publishes it.
 *
 * Pivots the fee records into the layout parents recognise: a table per
 * class level, a column per residency, and a total row — plus a separate
 * section for one-off fees paid on admission, which are not part of the
 * termly total.
 */
class FeeStructureSheet extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Fees Structure';

    protected static ?string $navigationLabel = 'Fees Structure';

    protected string $view = 'filament.pages.fee-structure-sheet';

    public ?int $termId = null;

    public function mount(): void
    {
        $this->termId = Term::current()?->getKey();
    }

    public function getTermProperty(): ?Term
    {
        return $this->termId ? Term::with('academicYear')->find($this->termId) : null;
    }

    public function getTermOptionsProperty(): array
    {
        return Term::query()
            ->where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
            ->toArray();
    }

    /**
     * Residency types become the columns — Day, Boarding, and any others
     * the school runs.
     *
     * @return Collection<int, ResidencyType>
     */
    public function getResidenciesProperty(): Collection
    {
        return ResidencyType::query()
            ->where('school_id', auth()->user()?->school_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Termly fees, grouped into the printed sections.
     *
     * Shape:
     *   [ level name => [ 'rows' => [...], 'totals' => [...] ] ]
     *
     * A row is one fee with an amount per residency. A fee with no
     * residency set costs the same to everyone, so its amount repeats
     * across the columns.
     */
    public function getSectionsProperty(): Collection
    {
        $term = $this->getTermProperty();

        if (! $term) {
            return collect();
        }

        $residencies = $this->getResidenciesProperty();

        $fees = FeeStructure::query()
            ->where('school_id', $term->school_id)
            ->where('is_active', true)
            ->where('frequency', 'per_term')
            ->where('term_id', $term->getKey())
            ->with(['schoolClass.classLevel', 'residencyType'])
            ->get();

        return $this->group($fees, $residencies);
    }

    /**
     * One-off fees — admission, uniform, bedding. Paid once, so they are
     * listed apart from the termly total rather than added into it.
     */
    public function getOneOffSectionsProperty(): Collection
    {
        $schoolId = auth()->user()?->school_id;

        $fees = FeeStructure::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('frequency', 'once')
            ->with(['schoolClass.classLevel', 'residencyType'])
            ->get();

        return $this->group($fees, $this->getResidenciesProperty());
    }

    /**
     * @param  Collection<int, FeeStructure>  $fees
     * @param  Collection<int, ResidencyType>  $residencies
     */
    protected function group(Collection $fees, Collection $residencies): Collection
    {
        return $fees
            // Classes at the same level share a fee structure, so collapse
            // them: four O-Level classes with the same tuition print once.
            //
            // One tier only -- the school is primary or secondary, so there
            // is no section above the level to group by.
            ->groupBy(fn (FeeStructure $fee) => $fee->schoolClass?->classLevel?->name ?? 'Unassigned')
            ->sortBy(fn ($group) => $group->first()?->schoolClass?->classLevel?->sort_order ?? 999)
            ->map(function (Collection $inLevel) use ($residencies) {
                $rows = $inLevel
                    ->groupBy('name')
                    ->map(function (Collection $sameName, string $feeName) use ($residencies) {
                        $amounts = [];

                        foreach ($residencies as $residency) {
                            // The fee for this residency, or the one that
                            // applies to everybody.
                            $match = $sameName->first(
                                fn (FeeStructure $f) => (int) $f->residency_type_id === (int) $residency->getKey()
                            ) ?? $sameName->first(
                                fn (FeeStructure $f) => $f->residency_type_id === null
                            );

                            $amounts[$residency->getKey()] = $match ? (float) $match->amount : null;
                        }

                        return [
                            'name' => $feeName,
                            'amounts' => $amounts,
                        ];
                    })
                    ->values();

                $totals = [];

                foreach ($residencies as $residency) {
                    $totals[$residency->getKey()] = $rows->sum(
                        fn (array $row) => $row['amounts'][$residency->getKey()] ?? 0
                    );
                }

                return ['rows' => $rows, 'totals' => $totals];
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole(['Super Admin', 'School Admin', 'Accountant', 'Bursar']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }
}
