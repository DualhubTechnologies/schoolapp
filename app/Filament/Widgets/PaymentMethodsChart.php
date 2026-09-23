<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\StudentPayment;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * How this term's fees were paid: cash, bank, mobile money, SchoolPay.
 */
class PaymentMethodsChart extends ChartWidget
{
    use SchoolScoped;

    protected static ?int $sort = 4;

    protected ?string $heading = 'How fees are paid';

    protected ?string $maxHeight = '260px';

    protected ?string $icon = 'heroicon-o-credit-card';

    protected ?string $iconColor = 'success';

    protected string $view = 'filament.widgets.branded-chart-widget';

    public static function canView(): bool
    {
        return static::userHandlesFees();
    }

    public function getDescription(): ?string
    {
        $term = $this->currentTerm();
        $total = array_sum($this->totals());

        return ($total > 0 ? static::shortMoney($total).' collected' : 'Nothing collected yet')
            .($term ? ', '.$term->label() : '');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, float> method => amount collected this term, largest first */
    protected function totals(): array
    {
        return once(function () {
            $term = $this->currentTerm();

            return StudentPayment::where('school_id', $this->schoolId())
                ->when($term, fn ($q) => $q->where(function ($q) use ($term) {
                    $q->where('term_id', $term->getKey());

                    if ($term->start_date && $term->end_date) {
                        $q->orWhere(fn ($q) => $q->whereNull('term_id')
                            ->whereBetween('paid_on', [$term->start_date, $term->end_date]));
                    }
                }))
                ->selectRaw('method, SUM(amount) as total')
                ->groupBy('method')
                ->orderByDesc('total')
                ->pluck('total', 'method')
                ->map(fn ($v) => (float) $v)
                ->all();
        });
    }

    protected function getData(): array
    {
        $totals = $this->totals();
        $sum = array_sum($totals);

        // An empty grey ring with a clear label, rather than a blank space.
        if ($sum <= 0) {
            return [
                'datasets' => [['data' => [1], 'backgroundColor' => ['#e4e8f0'], 'borderWidth' => 0]],
                'labels' => ['No payments yet'],
            ];
        }

        $colors = [
            'cash' => '#0f7a68',
            'bank' => '#1a5fa8',
            'mobile_money' => '#d97d0d',
            'schoolpay' => '#6b4f9e',
        ];

        return [
            'datasets' => [[
                'data' => array_values($totals),
                'backgroundColor' => collect($totals)->keys()->map(fn ($m) => $colors[$m] ?? '#94a3b8')->all(),
                'borderWidth' => 2,
                'borderColor' => '#ffffff',
                'hoverOffset' => 6,
            ]],
            // "Mobile money · 64%": the legend carries the share, the tooltip the amount.
            'labels' => collect($totals)
                ->map(fn ($v, $m) => (StudentPayment::METHODS[$m] ?? ucfirst((string) $m)).' · '.round($v / $sum * 100).'%')
                ->values()
                ->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                cutout: '68%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, boxWidth: 10 } },
                    tooltip: { callbacks: { label: (c) => c.label === 'No payments yet' ? ' No payments yet' : ' UGX ' + Number(c.parsed).toLocaleString() } },
                },
                scales: { x: { display: false }, y: { display: false } },
            }
        JS);
    }
}
