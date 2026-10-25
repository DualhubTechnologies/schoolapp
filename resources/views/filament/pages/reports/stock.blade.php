@php
    $rows = $this->rows();
    $total = collect($rows)->firstWhere('kind', 'total');
    $items = collect($rows)->where('kind', 'item');
    $issued = $this->issuedTo();
    $n = fn ($v) => number_format((float) $v);
    $signed = fn ($row) => $row['counted'] == 0 ? '-' : ($row['counted'] > 0 ? '+' : '-').$row['item']->amount(abs($row['counted']));
@endphp

<x-filament-panels::page>
    <x-report.toolbar>
        <div class="rpt-field">
            <span>Period</span>
            <div class="rpt-ranges">
                @foreach (['month' => 'This month', 'term' => 'This term', 'year' => 'This year'] as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')" @class(['rpt-range', 'is-on' => $this->range() === $key])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <x-report.field label="From"><x-filament::input type="date" wire:model.live="from" /></x-report.field>
        <x-report.field label="To"><x-filament::input type="date" wire:model.live="to" /></x-report.field>
        <x-report.field label="Kind of stock">
            <x-filament::input.select wire:model.live="category">
                <option value="">All stock</option>
                @foreach (\App\Models\StoreItem::CATEGORIES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-report.field>
    </x-report.toolbar>

    <x-report.paper :title="$this->getTitle()" :coverage="$this->coverage()">
        @if (! $total)
            <p class="rpt-empty">No store items to report on. Add them under Stores → Store Items.</p>
        @else
            <div class="rpt-tiles">
                <div class="is-main"><span>Stock worth now</span><b>UGX {{ $n($total['value']) }}</b></div>
                <div><span>Received (cost)</span><b>UGX {{ $n($total['received_cost']) }}</b></div>
                <div><span>Issued (cost)</span><b>UGX {{ $n($total['issued_cost']) }}</b></div>
                <div><span>Items running low</span><b>{{ $items->where('low', true)->count() }}</b></div>
            </div>

            <h4 class="rpt-h">Stock by item</h4>
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="rpt-num">Opening</th>
                            <th class="rpt-num">Received</th>
                            <th class="rpt-num">Issued</th>
                            <th class="rpt-num">Counted</th>
                            <th class="rpt-num">Closing</th>
                            <th class="rpt-num">Value (UGX)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items->groupBy(fn ($row) => \App\Models\StoreItem::CATEGORIES[$row['item']->category] ?? 'Other') as $category => $group)
                            <tr class="rpt-sub"><td colspan="7"><b>{{ $category }}</b></td></tr>
                            @foreach ($group as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="rpt-num">{{ $row['item']->amount($row['opening']) }}</td>
                                    <td class="rpt-num">{{ $row['received'] ? $row['item']->amount($row['received']) : '-' }}</td>
                                    <td class="rpt-num">{{ $row['issued'] ? $row['item']->amount($row['issued']) : '-' }}</td>
                                    <td @class(['rpt-num', 'rpt-bad' => $row['counted'] < 0])>{{ $signed($row) }}</td>
                                    <td @class(['rpt-num', 'rpt-strong', 'rpt-bad' => $row['low']])>{{ $row['item']->amount($row['closing']) }}{{ $row['low'] ? ' ▼' : '' }}</td>
                                    <td class="rpt-num">{{ $row['value'] === null ? '-' : $n($row['value']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr class="rpt-total">
                            <td colspan="6">Total value of stock</td>
                            <td class="rpt-num">{{ $n($total['value']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="rpt-note">Opening is what the store held when the period began, closing what it holds at its end. Counted is the correction after checking the shelf (a minus is stock missing). ▼ marks items at or below their warning level. Value is at the last price paid.</p>

            @if ($issued !== [])
                <h4 class="rpt-h">Where the stock went</h4>
                <div class="rpt-scroll">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>Issued to</th>
                                <th>What</th>
                                <th class="rpt-num">Times</th>
                                <th class="rpt-num">Cost (UGX)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($issued as $line)
                                <tr>
                                    <td class="rpt-strong">{{ $line['to'] }}</td>
                                    <td>{{ $line['items'] }}</td>
                                    <td class="rpt-num">{{ $line['issues'] }}</td>
                                    <td class="rpt-num">{{ $n($line['cost']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="rpt-total">
                                <td colspan="3">Total issued</td>
                                <td class="rpt-num">{{ $n(array_sum(array_column($issued, 'cost'))) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-report.paper>
</x-filament-panels::page>
