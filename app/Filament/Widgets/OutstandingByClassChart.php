<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\SchoolClass;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Which classes carry the most unpaid fees -- where collection effort
 * pays off first. Same charged/paid math as the "Largest outstanding
 * balances" table, summed per class instead of per student.
 */
class OutstandingByClassChart extends ChartWidget
{
    use SchoolScoped;

    protected static ?int $sort = 6;

    protected ?string $heading = 'Outstanding fees by class';

    protected ?string $description = 'Active students, charged minus paid, UGX';

    protected ?string $maxHeight = '280px';

    protected ?string $icon = 'heroicon-o-scale';

    protected ?string $iconColor = 'danger';

    protected string $view = 'filament.widgets.branded-chart-widget';

    protected const CHARGED = '(select coalesce(sum(amount - discount_amount), 0) from student_charges where student_charges.student_id = students.id)';

    protected const PAID = '(select coalesce(sum(amount), 0) from student_payments where student_payments.student_id = students.id and student_payments.voided_at is null)';

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
        $rows = SchoolClass::query()
            ->where('school_classes.school_id', $this->schoolId())
            ->join('students', 'students.school_class_id', '=', 'school_classes.id')
            ->where('students.status', 'active')
            ->selectRaw('school_classes.name as class_name')
            ->selectRaw('SUM('.self::CHARGED.' - '.self::PAID.') as balance')
            ->groupBy('school_classes.id', 'school_classes.name')
            ->havingRaw('SUM('.self::CHARGED.' - '.self::PAID.') > 0')
            ->orderByDesc('balance')
            ->limit(8)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Outstanding',
                'data' => $rows->pluck('balance')->map(fn ($v) => (float) $v)->all(),
                'backgroundColor' => '#eab8c3',
                'borderRadius' => 6,
                'maxBarThickness' => 26,
            ]],
            'labels' => $rows->pluck('class_name')->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => ' UGX ' + Number(c.parsed.x).toLocaleString() } },
                },
                scales: {
                    y: { grid: { display: false } },
                    x: {
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
