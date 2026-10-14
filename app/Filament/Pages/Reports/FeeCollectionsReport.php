<?php

namespace App\Filament\Pages\Reports;

use App\Models\SchoolClass;
use App\Models\StudentPayment;
use App\Models\Term;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Fees received over a period (this term so far by default): day by day
 * with each way of paying (cash, mobile money, bank, SchoolPay) and the
 * number of receipts, then by class. Voided receipts are left out. The
 * bursar's collections book, and what the bank deposits should add up to.
 */
class FeeCollectionsReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $title = 'Fee Collections';

    protected static ?string $slug = 'reports/fee-collections';

    protected string $view = 'filament.pages.reports.fee-collections';

    public string $from = '';

    public string $to = '';

    public static function about(): string
    {
        return 'Fees received day by day, by cash, mobile money, bank and SchoolPay, and by class.';
    }

    public function mount(): void
    {
        $this->setRange('term');
    }

    /**
     * The usual periods, ending today: today, this week, this month, this term.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function ranges(): array
    {
        $today = now()->toImmutable();
        $start = Term::current()?->start_date;
        $termStart = $start ? $start->toImmutable() : $today->startOfMonth();

        return collect([
            'today' => $today,
            'week' => $today->startOfWeek(),
            'month' => $today->startOfMonth(),
            'term' => $termStart,
        ])->map(fn (CarbonImmutable $from): array => [$from->toDateString(), $today->toDateString()])->all();
    }

    /** One tap for a usual period. */
    public function setRange(string $range): void
    {
        [$this->from, $this->to] = $this->ranges()[$range] ?? $this->ranges()['term'];
    }

    /** Which usual period the dates match, if any. */
    public function range(): ?string
    {
        $match = array_search([$this->from, $this->to], $this->ranges(), true);

        return $match === false ? null : $match;
    }

    public function fromDate(): CarbonImmutable
    {
        return $this->date($this->from, now()->toImmutable()->startOfMonth());
    }

    public function toDate(): CarbonImmutable
    {
        return $this->date($this->to, now()->toImmutable());
    }

    protected function date(string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        try {
            return $value !== '' ? CarbonImmutable::parse($value) : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }

    public function coverage(): string
    {
        return $this->fromDate()->format('j M Y').' – '.$this->toDate()->format('j M Y');
    }

    /** @return Collection<int, StudentPayment> */
    protected function payments(): Collection
    {
        return StudentPayment::query()
            ->with('student')
            ->where('school_id', auth()->user()?->school_id)
            ->whereBetween('paid_on', [$this->fromDate()->toDateString(), $this->toDate()->toDateString()])
            ->orderBy('paid_on')
            ->get();
    }

    /**
     * A row per day with money in, then the total.
     *
     * @return array<int, array{label: string, receipts: int, methods: array<string, float>, total: float, kind: string}>
     */
    public function byDay(): array
    {
        $payments = $this->payments();

        $rows = $payments
            ->groupBy(fn (StudentPayment $payment): string => CarbonImmutable::parse($payment->paid_on)->toDateString())
            ->map(fn (Collection $day, string $date): array => $this->sum(CarbonImmutable::parse($date)->format('D j M Y'), 'day', $day))
            ->values()
            ->all();

        return $payments->isEmpty() ? [] : [...$rows, $this->sum('Total', 'total', $payments)];
    }

    /**
     * Each class's receipts and total, most collected first.
     *
     * @return array<int, array{label: string, receipts: int, methods: array<string, float>, total: float, kind: string}>
     */
    public function byClass(): array
    {
        $classes = SchoolClass::where('school_id', auth()->user()?->school_id)->pluck('name', 'id');

        return $this->payments()
            ->groupBy(fn (StudentPayment $payment): string => $classes[$payment->student?->school_class_id] ?? 'No class')
            ->map(fn (Collection $paid, string $class): array => $this->sum($class, 'class', $paid))
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudentPayment>  $payments
     * @return array{label: string, receipts: int, methods: array<string, float>, total: float, kind: string}
     */
    protected function sum(string $label, string $kind, Collection $payments): array
    {
        return [
            'label' => $label,
            'kind' => $kind,
            'receipts' => $payments->count(),
            'methods' => collect(StudentPayment::METHODS)->map(fn (string $name, string $method): float => (float) $payments->where('method', $method)->sum('amount'))->all(),
            'total' => (float) $payments->sum('amount'),
        ];
    }

    public function sheet(): array
    {
        return [
            ['Date', 'Receipts', ...array_values(StudentPayment::METHODS), 'Total (UGX)'],
            array_map(fn (array $row): array => [$row['label'], $row['receipts'], ...array_values($row['methods']), $row['total']], $this->byDay()),
        ];
    }
}
