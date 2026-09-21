<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use Filament\Widgets\ChartWidget;
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

    protected ?string $description = 'Last 12 months, UGX';

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
        $from = now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn ($i) => $from->copy()->addMonths($i));

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
                    'backgroundColor' => '#bfd4ee',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Collected',
                    'data' => $months->map(fn ($m) => (float) ($collected[$m->format('Y-m')] ?? 0))->all(),
                    'backgroundColor' => '#1a5fa8',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $months->map(fn ($m) => $m->format('M y'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
