@php
    $groups = $this->groups();
    $conditions = $this->byCondition();
    $n = fn ($v) => number_format((float) $v);
    $items = array_sum(array_column($groups, 'items'));
    $cost = array_sum(array_column($groups, 'cost'));
@endphp

<x-filament-panels::page>
    <x-report.toolbar>
        <x-report.field label="Show">
            <x-filament::input.select wire:model.live="show">
                @foreach (\App\Filament\Pages\Reports\AssetRegisterReport::SHOW as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-report.field>
        <x-report.field label="Group by">
            <x-filament::input.select wire:model.live="groupBy">
                <option value="category">Category</option>
                <option value="location">Where it is</option>
            </x-filament::input.select>
        </x-report.field>
    </x-report.toolbar>

    <x-report.paper :title="$this->getTitle()" :coverage="$this->coverage()">
        @if ($groups === [])
            <p class="rpt-empty">No assets to list. Add them under Stores → Asset Register.</p>
        @else
            <div class="rpt-tiles">
                <div class="is-main"><span>Items</span><b>{{ $n($items) }}</b></div>
                <div><span>Cost</span><b>UGX {{ $n($cost) }}</b></div>
                @foreach ($conditions as $label => $count)
                    <div><span>{{ $label }}</span><b>{{ $n($count) }}</b></div>
                @endforeach
            </div>

            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Item</th>
                            <th class="rpt-num">Qty</th>
                            <th>{{ $this->groupBy === 'location' ? 'Category' : 'Where' }}</th>
                            <th>Condition</th>
                            <th>Looked after by</th>
                            <th>Acquired</th>
                            <th class="rpt-num">Cost (UGX)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr class="rpt-sub"><td colspan="8"><b>{{ $group['name'] }}</b></td></tr>
                            @foreach ($group['assets'] as $asset)
                                <tr>
                                    <td class="rpt-strong" style="white-space: nowrap">{{ $asset->tag_no }}</td>
                                    <td>{{ $asset->name }}@if ($asset->serial_no)<br><small>{{ $asset->serial_no }}</small>@endif</td>
                                    <td class="rpt-num">{{ $asset->quantity }}</td>
                                    <td>{{ $this->groupBy === 'location' ? (\App\Models\Asset::CATEGORIES[$asset->category] ?? '') : ($asset->location ?: '-') }}</td>
                                    <td @class(['rpt-bad' => in_array($asset->condition, ['poor', 'broken', 'lost'], true)])>{{ \App\Models\Asset::CONDITIONS[$asset->condition] ?? $asset->condition }}</td>
                                    <td>{{ $asset->assigned_to ?: '-' }}</td>
                                    <td>{{ $asset->acquired_on?->format('M Y') ?? '-' }}</td>
                                    <td class="rpt-num">{{ $asset->cost === null ? '-' : $n($asset->cost) }}</td>
                                </tr>
                            @endforeach
                            <tr class="rpt-sub">
                                <td colspan="2">{{ $group['name'] }}: subtotal</td>
                                <td class="rpt-num rpt-strong">{{ $n($group['items']) }}</td>
                                <td colspan="4"></td>
                                <td class="rpt-num rpt-strong">{{ $n($group['cost']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="rpt-total">
                            <td colspan="2">Total</td>
                            <td class="rpt-num">{{ $n($items) }}</td>
                            <td colspan="4"></td>
                            <td class="rpt-num">{{ $n($cost) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="rpt-note">Cost is what was paid for all the items under a tag together. Checked by: ____________________ &nbsp; Signature: ______________ &nbsp; Date: ____________</p>
        @endif
    </x-report.paper>
</x-filament-panels::page>
