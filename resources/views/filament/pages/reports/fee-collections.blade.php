@php
    $days = $this->byDay();
    $classes = $this->byClass();
    $accounts = $this->byBankAccount();
    $total = collect($days)->firstWhere('kind', 'total');
    $methods = \App\Models\StudentPayment::METHODS;
    $n = fn ($v) => number_format((float) $v);
    $dayCount = count($days) - 1;
@endphp

<x-filament-panels::page>
    <x-report.toolbar>
        <div class="rpt-field">
            <span>Period</span>
            <div class="rpt-ranges">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'term' => 'This term'] as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')" @class(['rpt-range', 'is-on' => $this->range() === $key])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <x-report.field label="From"><x-filament::input type="date" wire:model.live="from" /></x-report.field>
        <x-report.field label="To"><x-filament::input type="date" wire:model.live="to" /></x-report.field>
    </x-report.toolbar>

    <x-report.paper :title="$this->getTitle()" :coverage="$this->coverage()">
        @if (! $total)
            <p class="rpt-empty">No fees were received in this period.</p>
        @else
            <div class="rpt-tiles">
                <div class="is-main"><span>Collected</span><b>UGX {{ $n($total['total']) }}</b></div>
                <div><span>Receipts</span><b>{{ $n($total['receipts']) }}</b></div>
                <div><span>Days with payments</span><b>{{ $dayCount }}</b></div>
                @foreach ($methods as $key => $name)
                    @if ($total['methods'][$key] > 0)
                        <div><span>{{ $name }}</span><b>{{ $n($total['methods'][$key]) }}</b></div>
                    @endif
                @endforeach
            </div>

            <h4 class="rpt-h">Day by day</h4>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="rpt-num">Receipts</th>
                            @foreach ($methods as $name)
                                <th class="rpt-num">{{ $name }}</th>
                            @endforeach
                            <th class="rpt-num">Total (UGX)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($days as $row)
                            <tr @class(['rpt-total' => $row['kind'] === 'total'])>
                                <td>{{ $row['label'] }}</td>
                                <td class="rpt-num">{{ $row['receipts'] }}</td>
                                @foreach ($methods as $key => $name)
                                    <td class="rpt-num">{{ $row['methods'][$key] > 0 ? $n($row['methods'][$key]) : '—' }}</td>
                                @endforeach
                                <td class="rpt-num rpt-strong">{{ $n($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($accounts !== [])
                <h4 class="rpt-h">Paid by bank, by account</h4>
                <table class="rpt-table rpt-narrow">
                    <thead>
                        <tr><th>Account</th><th class="rpt-num">Receipts</th><th class="rpt-num">Paid in (UGX)</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="rpt-num">{{ $row['receipts'] }}</td>
                                <td class="rpt-num rpt-strong">{{ $n($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <h4 class="rpt-h">By class</h4>
            <table class="rpt-table rpt-narrow">
                <thead>
                    <tr><th>Class</th><th class="rpt-num">Receipts</th><th class="rpt-num">Collected (UGX)</th><th class="rpt-num">Share</th></tr>
                </thead>
                <tbody>
                    @foreach ($classes as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="rpt-num">{{ $row['receipts'] }}</td>
                            <td class="rpt-num rpt-strong">{{ $n($row['total']) }}</td>
                            <td class="rpt-num">{{ $total['total'] > 0 ? round($row['total'] / $total['total'] * 100) : 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-report.paper>
</x-filament-panels::page>
