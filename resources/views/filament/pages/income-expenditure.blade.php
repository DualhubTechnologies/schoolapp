@php
    $r = $this->report;
    $n = fn ($v) => number_format((float) $v);
    $maxMonth = $r ? max(1, $r['months']->max(fn ($m) => max($m['income'], $m['expense']))) : 1;
@endphp

<x-filament-panels::page>
    <div class="ie-top ie-no-print">
        <div class="ie-pickers">
            <div>
                <label class="ie-label">Period</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="period">
                        <option value="term">A term</option>
                        <option value="year">An academic year</option>
                        <option value="custom">Dates I choose</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            @if ($this->period === 'term')
                <div>
                    <label class="ie-label">Term</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="termId">
                            @foreach ($this->termOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @elseif ($this->period === 'year')
                <div>
                    <label class="ie-label">Academic year</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="yearId">
                            @foreach ($this->yearOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @else
                <div>
                    <label class="ie-label">From</label>
                    <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
                </div>
                <div>
                    <label class="ie-label">To</label>
                    <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper>
                </div>
            @endif
        </div>
        @if ($r)
            <div class="ie-actions">
                <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportCsv">Excel (CSV)</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-printer" onclick="window.print()">Print</x-filament::button>
            </div>
        @endif
    </div>

    @if (! $r)
        <div class="ie-empty">This period has no dates set. Give the term or academic year a start and end date under Academics.</div>
    @else
        <div class="ie-print-title">{{ auth()->user()->school?->name }} — Income vs Expenditure — {{ $r['label'] }}</div>

        <div class="ie-tiles">
            <div class="ie-tile">
                <span>Received</span>
                <strong class="ie-in">UGX {{ $n($r['totals']['income']) }}</strong>
                <em>Budget {{ $n($r['totals']['income_budget']) }}</em>
            </div>
            <div class="ie-tile">
                <span>Spent</span>
                <strong class="ie-out">UGX {{ $n($r['totals']['expense']) }}</strong>
                <em>Budget {{ $n($r['totals']['expense_budget']) }}</em>
            </div>
            <div @class(['ie-tile', 'ie-tile-good' => $r['totals']['balance'] >= 0, 'ie-tile-bad' => $r['totals']['balance'] < 0])>
                <span>{{ $r['totals']['balance'] >= 0 ? 'Surplus' : 'Deficit' }}</span>
                <strong>UGX {{ $n(abs($r['totals']['balance'])) }}</strong>
                <em>{{ $r['label'] }}</em>
            </div>
            <div class="ie-tile">
                <span>Budget used</span>
                <strong>{{ $r['totals']['expense_budget'] > 0 ? round($r['totals']['expense'] / $r['totals']['expense_budget'] * 100) . '%' : '—' }}</strong>
                <em>of planned spending</em>
            </div>
        </div>

        <div class="ie-grid">
            @foreach (['income' => ['Income', 'Received'], 'expense' => ['Expenditure', 'Spent']] as $type => [$heading, $actualLabel])
                <div class="ie-card">
                    <div class="ie-head">{{ $heading }}</div>
                    <table class="ie-table">
                        <thead><tr><th>Category</th><th class="ie-r">Budget</th><th class="ie-r">{{ $actualLabel }}</th><th class="ie-r">Variance</th><th class="ie-r">%</th></tr></thead>
                        <tbody>
                            @foreach ($r[$type] as $row)
                                @php
                                    // Spending above budget, or income below it, is the bad direction.
                                    $bad = $row['budget'] > 0 && ($type === 'expense' ? $row['variance'] > 0 : $row['variance'] < 0);
                                @endphp
                                <tr>
                                    <td>{{ $row['category']->name }}@if ($row['category']->isAutomatic())<span class="ie-auto">auto</span>@endif</td>
                                    <td class="ie-r ie-muted">{{ $row['budget'] ? $n($row['budget']) : '—' }}</td>
                                    <td class="ie-r ie-strong">{{ $row['actual'] ? $n($row['actual']) : '—' }}</td>
                                    <td @class(['ie-r', 'ie-bad' => $bad, 'ie-muted' => ! $bad])>{{ $row['budget'] ? ($row['variance'] >= 0 ? '+' : '−') . $n(abs($row['variance'])) : '' }}</td>
                                    <td class="ie-r">
                                        @if ($row['percent'] !== null)
                                            <span @class(['ie-pill', 'ie-pill-bad' => $bad])>{{ $row['percent'] }}%</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="ie-r">{{ $n($r['totals'][$type . '_budget']) }}</td>
                                <td class="ie-r">{{ $n($r['totals'][$type]) }}</td>
                                <td></td><td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach
        </div>

        @if ($r['months']->count() > 1)
            <div class="ie-card">
                <div class="ie-head">Month by month <span class="ie-legend"><i class="ie-sw ie-sw-in"></i>Received <i class="ie-sw ie-sw-out"></i>Spent</span></div>
                <div class="ie-months">
                    @foreach ($r['months'] as $m)
                        <div class="ie-month">
                            <div class="ie-month-label">{{ $m['month']->format('M Y') }}</div>
                            <div class="ie-bars">
                                <div class="ie-bar-row"><div class="ie-bar ie-bar-in" style="width: {{ $m['income'] / $maxMonth * 100 }}%"></div><span>{{ $n($m['income']) }}</span></div>
                                <div class="ie-bar-row"><div class="ie-bar ie-bar-out" style="width: {{ $m['expense'] / $maxMonth * 100 }}%"></div><span>{{ $n($m['expense']) }}</span></div>
                            </div>
                            <div @class(['ie-month-net', 'ie-in' => $m['income'] >= $m['expense'], 'ie-out' => $m['income'] < $m['expense']])>{{ $m['income'] >= $m['expense'] ? '+' : '−' }}{{ $n(abs($m['income'] - $m['expense'])) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <style>
        .ie-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1rem; }
        .ie-pickers { display: flex; flex-wrap: wrap; gap: .9rem; }
        .ie-pickers > div { min-width: 12rem; }
        .ie-actions { display: flex; gap: .5rem; }
        .ie-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .ie-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .ie-print-title { display: none; }
        .ie-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .9rem; }
        @media (min-width: 1000px) { .ie-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .ie-tile { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: .9rem 1.1rem; }
        .ie-tile span { display: block; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        .ie-tile strong { display: block; font-size: 1.35rem; font-weight: 800; color: #16233a; margin: .15rem 0; font-variant-numeric: tabular-nums; }
        .ie-tile em { font-style: normal; font-size: .75rem; color: #64748b; }
        .ie-tile-good { background: #f0fdf4; border-color: #bbf7d0; }
        .ie-tile-good strong { color: #15803d; }
        .ie-tile-bad { background: #fef2f2; border-color: #fecaca; }
        .ie-tile-bad strong { color: #b91c1c; }
        .ie-in { color: #15803d !important; }
        .ie-out { color: #b91c1c !important; }
        .ie-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1200px) { .ie-grid { grid-template-columns: 1fr 1fr; align-items: start; } }
        .ie-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .ie-head { display: flex; justify-content: space-between; align-items: center; padding: .8rem 1.1rem; font-weight: 700; color: #16233a; border-bottom: 1px solid #eef2f7; }
        .ie-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .ie-table th { text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; background: #f8fafc; padding: .55rem .8rem; border-bottom: 1px solid #e4e8f0; }
        .ie-table td { padding: .5rem .8rem; border-bottom: 1px solid #f1f5f9; }
        .ie-table tfoot td { font-weight: 800; background: #f8fafc; border-top: 1.5px solid #1e3a5f; }
        .ie-r { text-align: right !important; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ie-muted { color: #64748b; }
        .ie-strong { font-weight: 700; color: #16233a; }
        .ie-bad { color: #b91c1c; font-weight: 600; }
        .ie-auto { display: inline-block; margin-left: .35rem; font-size: .65rem; padding: .02rem .35rem; border-radius: 999px; background: #eef4fb; color: #1a5fa8; }
        .ie-pill { display: inline-block; font-size: .72rem; padding: .08rem .45rem; border-radius: 999px; background: #eef4fb; color: #1e3a5f; }
        .ie-pill-bad { background: #fee2e2; color: #b91c1c; }
        .ie-legend { font-size: .75rem; font-weight: 500; color: #64748b; display: flex; align-items: center; gap: .35rem; }
        .ie-sw { display: inline-block; width: .7rem; height: .7rem; border-radius: 2px; margin-left: .5rem; }
        .ie-sw-in, .ie-bar-in { background: #16a34a; }
        .ie-sw-out, .ie-bar-out { background: #dc2626; }
        .ie-months { padding: .5rem 1.1rem 1rem; }
        .ie-month { display: grid; grid-template-columns: 6rem 1fr 8rem; gap: .8rem; align-items: center; padding: .45rem 0; border-bottom: 1px solid #f1f5f9; }
        .ie-month-label { font-weight: 600; color: #16233a; font-size: .82rem; }
        .ie-bar-row { display: flex; align-items: center; gap: .5rem; height: 1rem; }
        .ie-bar-row span { font-size: .72rem; color: #64748b; font-variant-numeric: tabular-nums; }
        .ie-bar-row .ie-bar { height: .55rem; border-radius: 999px; min-width: 2px; }
        .ie-month-net { text-align: right; font-weight: 700; font-size: .82rem; font-variant-numeric: tabular-nums; }
        @media print {
            @page { size: A4; margin: 10mm; }
            .ie-no-print, .fi-sidebar, .fi-topbar, .sh-topbar, .fi-header, .fi-footer { display: none !important; }
            .fi-main, .fi-page, .fi-main-ctn { padding: 0 !important; margin: 0 !important; }
            .ie-print-title { display: block; font-weight: 800; font-size: 1.1rem; color: #1e3a5f; margin-bottom: .5rem; }
            .ie-grid { grid-template-columns: 1fr 1fr; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</x-filament-panels::page>
