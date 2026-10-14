@php
    $days = $this->byDay();
    $classes = $this->byClass();
    $total = collect($days)->firstWhere('kind', 'total');
    $methods = \App\Models\StudentPayment::METHODS;
    $n = fn ($v) => number_format((float) $v);
    $dayCount = count($days) - 1;
@endphp

<x-filament-panels::page>
    <x-report.toolbar>
        <div class="rp-field">
            <span>Period</span>
            <div class="rp-ranges">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'term' => 'This term'] as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')" @class(['rp-range', 'is-on' => $this->range() === $key])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <x-report.field label="From"><x-filament::input type="date" wire:model.live="from" /></x-report.field>
        <x-report.field label="To"><x-filament::input type="date" wire:model.live="to" /></x-report.field>
    </x-report.toolbar>

    <x-report.paper :title="$this->getTitle()" :coverage="$this->coverage()">
        @if (! $total)
            <p class="rp-empty">No fees were received in this period.</p>
        @else
            <div class="rp-tiles">
                <div class="is-main"><span>Collected</span><b>UGX {{ $n($total['total']) }}</b></div>
                <div><span>Receipts</span><b>{{ $n($total['receipts']) }}</b></div>
                <div><span>Days with payments</span><b>{{ $dayCount }}</b></div>
                @foreach ($methods as $key => $name)
                    @if ($total['methods'][$key] > 0)
                        <div><span>{{ $name }}</span><b>{{ $n($total['methods'][$key]) }}</b></div>
                    @endif
                @endforeach
            </div>

            <h4 class="rp-h">Day by day</h4>
            <div class="rp-scroll">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="rp-num">Receipts</th>
                            @foreach ($methods as $name)
                                <th class="rp-num">{{ $name }}</th>
                            @endforeach
                            <th class="rp-num">Total (UGX)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($days as $row)
                            <tr @class(['rp-total' => $row['kind'] === 'total'])>
                                <td>{{ $row['label'] }}</td>
                                <td class="rp-num">{{ $row['receipts'] }}</td>
                                @foreach ($methods as $key => $name)
                                    <td class="rp-num">{{ $row['methods'][$key] > 0 ? $n($row['methods'][$key]) : '—' }}</td>
                                @endforeach
                                <td class="rp-num rp-strong">{{ $n($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h4 class="rp-h">By class</h4>
            <table class="rp-table rp-narrow">
                <thead>
                    <tr><th>Class</th><th class="rp-num">Receipts</th><th class="rp-num">Collected (UGX)</th><th class="rp-num">Share</th></tr>
                </thead>
                <tbody>
                    @foreach ($classes as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="rp-num">{{ $row['receipts'] }}</td>
                            <td class="rp-num rp-strong">{{ $n($row['total']) }}</td>
                            <td class="rp-num">{{ $total['total'] > 0 ? round($row['total'] / $total['total'] * 100) : 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-report.paper>
</x-filament-panels::page>
