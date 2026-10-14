<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\ResidencyType;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * The fees structure as a Ugandan school publishes it, worked out from the
 * fee records for one term:
 *
 *   Fees per term       one table per group of classes that pay the same
 *                       (S.1 – S.3, S.4...), a column per residency (Day,
 *                       Boarding), a total row
 *   On admission        what a new learner pays once, in their first term
 *                       (admission, development, uniform...)
 *   First term in all   per class group, fees per term + admission
 *   Other charges       fees billed only where they apply (UNEB, trips...)
 *
 * A fee with no residency costs the same to everyone, so its amount
 * repeats across the columns. A school without residency types gets a
 * single "Amount" column.
 */
class FeeSheet
{
    /** @var Collection<int, SchoolClass> the school's classes, in order */
    protected Collection $classes;

    /** @var array<int, string> column key => heading; 0 when the school has no residency types */
    protected array $columns;

    public function __construct(protected Term $term)
    {
        $this->classes = SchoolClass::where('school_id', $term->school_id)
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->values();

        $residencies = ResidencyType::where('school_id', $term->school_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();

        $this->columns = $residencies ?: [0 => 'Amount'];
    }

    /** @return array<int, string> */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, array{classes: string, rows: array<int, array{name: string, amounts: array<int, float|null>}>, totals: array<int, float>}>
     */
    public function termly(): array
    {
        return $this->groups(
            FeeStructure::termlyFor($this->term)->where('applies_to', '!=', 'new_only'),
        );
    }

    /**
     * Paid once, by a new learner in their first term: admission fees, and
     * termly fees charged to new learners only.
     *
     * @return array<int, array{classes: string, rows: array<int, array{name: string, amounts: array<int, float|null>}>, totals: array<int, float>}>
     */
    public function admission(): array
    {
        return $this->groups($this->admissionFees());
    }

    /**
     * What a new learner pays in the first term, per group of classes.
     *
     * @return array<int, array{classes: string, totals: array<int, float>}>
     */
    public function firstTerm(): array
    {
        $admission = $this->admissionFees();

        if ($admission->isEmpty()) {
            return [];
        }

        $fees = FeeStructure::termlyFor($this->term)->where('applies_to', '!=', 'new_only')->concat($admission);

        return collect($this->groups($fees))
            ->map(fn (array $group): array => ['classes' => $group['classes'], 'totals' => $group['totals']])
            ->all();
    }

    /**
     * Charges billed only to the learners they concern, e.g. "UNEB
     * registration — S.4: 164,000 · S.6: 186,000".
     *
     * @return array<int, array{name: string, detail: string}>
     */
    public function other(): array
    {
        return FeeStructure::query()
            ->where('school_id', $this->term->school_id)
            ->where('frequency', 'on_demand')
            ->where('is_active', true)
            ->get()
            ->groupBy(fn (FeeStructure $fee): string => trim($fee->name))
            ->sortKeys()
            ->map(fn (Collection $fees, string $name): array => [
                'name' => $name,
                'detail' => $fees
                    ->groupBy(fn (FeeStructure $fee): string => $fee->residency_type_id.'|'.(float) $fee->amount)
                    ->map(function (Collection $same): string {
                        /** @var FeeStructure $first */
                        $first = $same->first();
                        $residency = $first->residency_type_id ? ($this->columns[(int) $first->residency_type_id] ?? null) : null;
                        $who = $this->classLabel($same->pluck('school_class_id')->all())
                            .($residency ? ' ('.mb_strtolower($residency).')' : '');

                        return $who.': '.number_format((float) $first->amount);
                    })
                    ->values()
                    ->implode(' · '),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, FeeStructure> */
    protected function admissionFees(): Collection
    {
        $once = FeeStructure::query()
            ->where('school_id', $this->term->school_id)
            ->where('frequency', 'once')
            ->where('is_active', true)
            ->get();

        return FeeStructure::termlyFor($this->term)->where('applies_to', 'new_only')->concat($once)->values();
    }

    /**
     * One table per group of classes with exactly the same fees.
     *
     * @param  Collection<int, FeeStructure>  $fees
     * @return array<int, array{classes: string, rows: array<int, array{name: string, amounts: array<int, float|null>}>, totals: array<int, float>}>
     */
    protected function groups(Collection $fees): array
    {
        // Every table lists its items in the same order: the biggest
        // (tuition) first, then the smaller levies.
        $order = $fees->groupBy(fn (FeeStructure $fee): string => trim($fee->name))
            ->map(fn (Collection $same): float => (float) $same->max('amount'))
            ->sortKeys()
            ->sortDesc()
            ->keys()
            ->flip();

        return $this->classes
            ->map(function (SchoolClass $class) use ($fees, $order): ?array {
                $rows = $fees->where('school_class_id', $class->getKey())
                    ->groupBy(fn (FeeStructure $fee): string => trim($fee->name))
                    ->sortBy(fn (Collection $same, string $name): int => $order[$name] ?? PHP_INT_MAX)
                    ->map(fn (Collection $same, string $name): array => [
                        'name' => $name,
                        'amounts' => $this->amounts($same),
                    ])
                    ->values()
                    ->all();

                return $rows ? ['class' => $class->getKey(), 'rows' => $rows] : null;
            })
            ->filter()
            ->groupBy(fn (array $class): string => json_encode($class['rows']) ?: '')
            ->map(function (Collection $classes): array {
                $rows = $classes->first()['rows'] ?? [];

                return [
                    'classes' => $this->classLabel($classes->pluck('class')->all()),
                    'rows' => $rows,
                    'totals' => collect($this->columns)
                        ->map(fn (string $heading, int $column): float => (float) collect($rows)->sum(fn (array $row): float => $row['amounts'][$column] ?? 0))
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * One fee's amount in each column: the version for that residency, or
     * the one everybody pays.
     *
     * @param  Collection<int, FeeStructure>  $versions
     * @return array<int, float|null>
     */
    protected function amounts(Collection $versions): array
    {
        $amounts = [];

        foreach (array_keys($this->columns) as $column) {
            $fee = $versions->first(fn (FeeStructure $f): bool => $column !== 0 && (int) $f->residency_type_id === $column)
                ?? $versions->first(fn (FeeStructure $f): bool => $f->residency_type_id === null)
                ?? ($column === 0 ? $versions->first() : null);

            $amounts[$column] = $fee ? (float) $fee->amount : null;
        }

        return $amounts;
    }

    /**
     * "S.1 – S.3" for classes that follow each other, "S.5 and S.6" for
     * two, "S.1, S.3 and S.5" otherwise.
     *
     * @param  array<int, int|string|null>  $classIds
     */
    public function classLabel(array $classIds): string
    {
        $picked = $this->classes->filter(fn (SchoolClass $class): bool => in_array($class->getKey(), $classIds));
        $positions = $picked->keys()->values();
        $names = $picked->map(fn (SchoolClass $class): string => $class->name)->values();

        $consecutive = $positions->count() > 2 && $positions->last() - $positions->first() === $positions->count() - 1;

        return match (true) {
            $names->isEmpty() => 'All classes',
            $consecutive => $names->first().' – '.$names->last(),
            $names->count() === 1 => $names->first(),
            default => $names->slice(0, -1)->implode(', ').' and '.$names->last(),
        };
    }
}
