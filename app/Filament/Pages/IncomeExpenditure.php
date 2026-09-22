<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\Finance\FinanceReport;
use App\Support\FinanceAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the school received against what it spent, with the budget beside
 * each line: for a term, an academic year, or any dates.
 */
class IncomeExpenditure extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Income vs Expenditure';

    protected string $view = 'filament.pages.income-expenditure';

    /** term | year | custom */
    public string $period = 'term';

    public ?int $termId = null;

    public ?int $yearId = null;

    public ?string $from = null;

    public ?string $to = null;

    public static function canAccess(): bool
    {
        return FinanceAccess::allowed();
    }

    public function mount(): void
    {
        $schoolId = auth()->user()->school_id;
        $this->termId = Term::current($schoolId)?->getKey();
        $this->yearId = AcademicYear::current($schoolId)?->getKey();
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return Collection<int, string> */
    public function termOptions(): Collection
    {
        return Term::where('school_id', auth()->user()->school_id)->with('academicYear')->get()
            ->sortByDesc(fn (Term $t) => $t->sortKey())
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()]);
    }

    /** @return Collection<int, string> */
    public function yearOptions(): Collection
    {
        return AcademicYear::where('school_id', auth()->user()->school_id)->orderByDesc('start_date')->pluck('name', 'id');
    }

    #[Computed]
    public function report(): ?array
    {
        $schoolId = auth()->user()->school_id;

        [$from, $to, $terms, $label] = match ($this->period) {
            'year' => $this->yearRange($schoolId),
            'custom' => [
                $this->from ? Carbon::parse($this->from) : null,
                $this->to ? Carbon::parse($this->to) : null,
                null,
                null,
            ],
            default => $this->termRange($schoolId),
        };

        if (! $from || ! $to || $from->gt($to)) {
            return null;
        }

        return app(FinanceReport::class)->build($schoolId, $from->startOfDay(), $to->endOfDay(), $terms)
            + ['label' => $label ?? ($from->format('j M Y') . ' – ' . $to->format('j M Y'))];
    }

    protected function termRange(int $schoolId): array
    {
        $term = $this->termId ? Term::where('school_id', $schoolId)->with('academicYear')->find($this->termId) : null;

        return $term?->start_date && $term?->end_date
            ? [$term->start_date->copy(), $term->end_date->copy(), collect([$term]), $term->label()]
            : [null, null, null, null];
    }

    protected function yearRange(int $schoolId): array
    {
        $year = $this->yearId ? AcademicYear::where('school_id', $schoolId)->find($this->yearId) : null;
        $terms = $year ? Term::where('academic_year_id', $year->getKey())->get() : collect();

        $from = $year?->start_date ?? $terms->min('start_date');
        $to = $year?->end_date ?? $terms->max('end_date');

        return $from && $to ? [Carbon::parse($from), Carbon::parse($to), $terms, 'Academic year ' . $year->name] : [null, null, null, null];
    }

    public function exportCsv(): ?StreamedResponse
    {
        $r = $this->report;

        if (! $r) {
            return null;
        }

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Income vs Expenditure', $r['label']]);
            foreach (['income' => 'INCOME', 'expense' => 'EXPENDITURE'] as $type => $heading) {
                fputcsv($out, []);
                fputcsv($out, [$heading, 'Budget', 'Actual', 'Variance', '% of budget']);
                foreach ($r[$type] as $row) {
                    fputcsv($out, [$row['category']->name, $row['budget'], $row['actual'], $row['variance'], $row['percent']]);
                }
                fputcsv($out, ['Total', $r['totals'][$type . '_budget'], $r['totals'][$type]]);
            }
            fputcsv($out, []);
            fputcsv($out, [$r['totals']['balance'] >= 0 ? 'Surplus' : 'Deficit', '', abs($r['totals']['balance'])]);
            fclose($out);
        }, str('income-vs-expenditure ' . $r['label'])->slug() . '.csv', ['Content-Type' => 'text/csv']);
    }
}
