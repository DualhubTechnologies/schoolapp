<?php

namespace App\Filament\Pages\Reports;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Term;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * A term's fees, class by class: what was billed (after discounts and
 * bursaries), what has been paid towards that term, what is still owed,
 * the share collected, and how many learners owe or have cleared. The
 * head teacher's view of where the term's money stands.
 */
class FeesByClassReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Fees by Class';

    protected static ?string $slug = 'reports/fees-by-class';

    protected string $view = 'filament.pages.reports.fees-by-class';

    public ?int $termId = null;

    public static function about(): string
    {
        return 'For a term: each class\'s fees billed, paid and still owed, the share collected, and who owes.';
    }

    public function mount(): void
    {
        $this->termId = Term::current()?->getKey();
    }

    public function term(): ?Term
    {
        return $this->termId ? Term::with('academicYear')->where('school_id', auth()->user()?->school_id)->find($this->termId) : null;
    }

    /** @return array<int, string> */
    public function termOptions(): array
    {
        return Term::where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->sortByDesc(fn (Term $term): string => $term->sortKey())
            ->mapWithKeys(fn (Term $term): array => [$term->getKey() => $term->label()])
            ->all();
    }

    public function coverage(): string
    {
        return $this->term()?->label() ?? '';
    }

    /**
     * A row per class with fees billed this term, then the school total.
     *
     * @return array<int, array{label: string, kind: string, learners: int, billed: float, paid: float, balance: float, percent: int, owing: int, cleared: int}>
     */
    public function rows(): array
    {
        $term = $this->term();

        if (! $term) {
            return [];
        }

        $billed = StudentCharge::query()
            ->where('term_id', $term->getKey())
            ->selectRaw('student_id, sum(amount - coalesce(discount_amount, 0)) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $paid = StudentPayment::query()
            ->where('term_id', $term->getKey())
            ->selectRaw('student_id, sum(amount) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $learners = Student::query()
            ->where('school_id', $term->school_id)
            ->whereIn('id', $billed->keys()->merge($paid->keys())->unique()->all())
            ->get(['id', 'school_class_id'])
            ->map(fn (Student $student): array => [
                'class' => $student->school_class_id,
                'billed' => (float) ($billed[$student->getKey()] ?? 0),
                'paid' => (float) ($paid[$student->getKey()] ?? 0),
            ]);

        $rows = SchoolClass::inOrder($term->school_id)
            ->map(fn (SchoolClass $class): array => $this->sum($class->name, 'class', $learners->where('class', $class->getKey())->all()))
            ->filter(fn (array $row): bool => $row['learners'] > 0)
            ->values()
            ->all();

        $unplaced = $learners->whereNull('class');

        if ($unplaced->isNotEmpty()) {
            $rows[] = $this->sum('No class', 'class', $unplaced->all());
        }

        return $learners->isEmpty() ? [] : [...$rows, $this->sum('Total', 'total', $learners->all())];
    }

    /**
     * @param  array<int, array{class: int|null, billed: float, paid: float}>  $learners
     * @return array{label: string, kind: string, learners: int, billed: float, paid: float, balance: float, percent: int, owing: int, cleared: int}
     */
    protected function sum(string $label, string $kind, array $learners): array
    {
        $learners = collect($learners);
        $billed = (float) $learners->sum('billed');
        $paid = (float) $learners->sum('paid');

        return [
            'label' => $label,
            'kind' => $kind,
            'learners' => $learners->count(),
            'billed' => $billed,
            'paid' => $paid,
            'balance' => $billed - $paid,
            'percent' => $billed > 0 ? (int) floor(min($paid, $billed) / $billed * 100) : 0,
            'owing' => $learners->filter(fn (array $learner): bool => $learner['billed'] - $learner['paid'] > 0)->count(),
            'cleared' => $learners->filter(fn (array $learner): bool => $learner['billed'] > 0 && $learner['billed'] - $learner['paid'] <= 0)->count(),
        ];
    }

    public function sheet(): array
    {
        return [
            ['Class', 'Learners', 'Billed (UGX)', 'Paid (UGX)', 'Balance (UGX)', '% collected', 'Owing', 'Cleared'],
            array_map(fn (array $row): array => [
                $row['label'], $row['learners'], $row['billed'], $row['paid'], $row['balance'], $row['percent'], $row['owing'], $row['cleared'],
            ], $this->rows()),
        ];
    }
}
