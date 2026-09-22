<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Billed vs collected, month by month, for the last twelve months.
 */
class FeeCollectionChart extends ChartWidget
{
    use SchoolScoped;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Fees billed vs collected';

    protected ?string $description = 'By month, UGX — hover a bar for the exact amount';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return static::userHandlesFees();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // Up to 12 months, starting at the first month with any fees, but
        // never fewer than 6, so a new school does not see a row of empty months.
        $earliest = now()->startOfMonth()->subMonths(11);
        $first = collect([
            StudentCharge::where('school_id', $this->schoolId())->where('charged_on', '>=', $earliest)->min('charged_on'),
            StudentPayment::where('school_id', $this->schoolId())->where('paid_on', '>=', $earliest)->min('paid_on'),
        ])->filter()->min();
        $from = $first ? Carbon::parse($first)->startOfMonth() : $earliest;
        $from = $from->min(now()->startOfMonth()->subMonths(5))->max($earliest);
        $count = (int) $from->diffInMonths(now()->startOfMonth()) + 1;
        $months = collect(range(0, $count - 1))->map(fn ($i) => $from->copy()->addMonths($i));

        $billed = StudentCharge::where('school_id', $this->schoolId())
            ->where('charged_on', '>=', $from)
            ->selectRaw("DATE_FORMAT(charged_on, '%Y-%m') as ym, SUM(amount - discount_amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $collected = StudentPayment::where('school_id', $this->schoolId())
            ->where('paid_on', '>=', $from)
            ->selectRaw("DATE_FORMAT(paid_on, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        return [
            'datasets' => [
                [
                    'label' => 'Billed',
                    'data' => $months->map(fn ($m) => (float) ($billed[$m->format('Y-m')] ?? 0))->all(),
                    'backgroundColor' => '#bcd3ee',
                    'borderRadius' => 6,
                    'maxBarThickness' => 34,
                ],
                [
                    'label' => 'Collected',
                    'data' => $months->map(fn ($m) => (float) ($collected[$m->format('Y-m')] ?? 0))->all(),
                    'backgroundColor' => '#1a5fa8',
                    'borderRadius' => 6,
                    'maxBarThickness' => 34,
                ],
            ],
            'labels' => $months->map(fn ($m) => $m->format('M y'))->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', padding: 18 } },
                    tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': UGX ' + Number(c.parsed.y).toLocaleString() } },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#eef2f7' },
                        ticks: { callback: (v) => v >= 1e6 ? (v / 1e6) + 'M' : (v >= 1e3 ? (v / 1e3) + 'K' : v) },
                    },
                },
            }
        JS);
    }
}
