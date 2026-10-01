<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\Student;
use App\Support\Sql;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * New admissions each month, for the last twelve months -- growth
 * momentum at a glance, alongside the class-by-class headcount.
 */
class AdmissionsTrendChart extends ChartWidget
{
    use SchoolScoped;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Admissions trend';

    protected ?string $description = 'New students admitted, by month';

    protected ?string $maxHeight = '260px';

    protected ?string $icon = 'heroicon-o-arrow-trending-up';

    protected ?string $iconColor = 'warning';

    protected string $view = 'filament.widgets.branded-chart-widget';

    public static function canView(): bool
    {
        return auth()->user()?->school_id !== null;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $from = now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn ($i) => $from->copy()->addMonths($i));

        $admitted = Student::where('school_id', $this->schoolId())
            ->whereNotNull('admission_date')
            ->where('admission_date', '>=', $from)
            ->selectRaw(Sql::yearMonth('admission_date').' as ym, COUNT(*) as n')
            ->groupBy('ym')
            ->pluck('n', 'ym');

        return [
            'datasets' => [[
                'label' => 'Admissions',
                'data' => $months->map(fn ($m) => (int) ($admitted[$m->format('Y-m')] ?? 0))->all(),
                'borderColor' => '#b5721a',
                'backgroundColor' => 'rgba(181, 114, 26, 0.12)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 3,
                'pointBackgroundColor' => '#b5721a',
            ]],
            'labels' => $months->map(fn ($m) => $m->format('M y'))->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => ' ' + Number(c.parsed.y).toLocaleString() + ' ' + (c.parsed.y === 1 ? 'student' : 'students') } },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#eef2f7' },
                        ticks: { precision: 0 },
                    },
                },
            }
        JS);
    }
}
