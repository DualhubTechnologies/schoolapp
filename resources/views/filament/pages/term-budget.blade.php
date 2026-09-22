@php
    $actuals = $this->actuals;
    $n = fn ($v) => number_format((float) $v);
    $sum = fn ($type) => collect($this->categories[$type] ?? [])->sum(fn ($c) => (float) str_replace(',', '', (string) ($this->amounts[$c->id] ?? 0)));
    $incomeTotal = $sum('income');
    $expenseTotal = $sum('expense');
@endphp

<x-filament-panels::page>
    <div class="tb-bar">
        <div class="tb-term">
            <label class="tb-label">Term</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="termId">
                    @foreach ($this->termOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div class="tb-actions">
            <x-filament::button color="gray" icon="heroicon-o-document-duplicate" wire:click="copyPrevious">Copy from last term</x-filament::button>
            <x-filament::button icon="heroicon-o-check" wire:click="save">Save budget</x-filament::button>
        </div>
    </div>

    <div class="tb-tiles">
        <div class="tb-tile"><span>Planned income</span><strong class="tb-in">UGX {{ $n($incomeTotal) }}</strong></div>
        <div class="tb-tile"><span>Planned spending</span><strong class="tb-out">UGX {{ $n($expenseTotal) }}</strong></div>
        <div class="tb-tile"><span>Planned {{ $incomeTotal - $expenseTotal >= 0 ? 'surplus' : 'deficit' }}</span><strong @class(['tb-in' => $incomeTotal >= $expenseTotal, 'tb-out' => $incomeTotal < $expenseTotal])>UGX {{ $n(abs($incomeTotal - $expenseTotal)) }}</strong></div>
    </div>

    <div class="tb-grid">
        @foreach (['income' => 'Income', 'expense' => 'Expenditure'] as $type => $heading)
            <div class="tb-card">
                <div class="tb-head">{{ $heading }}</div>
                <table class="tb-table">
                    <thead><tr><th>Category</th><th class="tb-r">Budget (UGX)</th><th class="tb-r">Actual so far</th></tr></thead>
                    <tbody>
                        @foreach ($this->categories[$type] ?? [] as $category)
                            <tr wire:key="b-{{ $category->id }}">
                                <td>
                                    {{ $category->name }}
                                    @if ($category->isAutomatic())<span class="tb-auto">automatic</span>@endif
                                </td>
                                <td class="tb-r">
                                    <input type="text" inputmode="numeric" class="tb-input" wire:model.blur="amounts.{{ $category->id }}" placeholder="0">
                                </td>
                                <td class="tb-r tb-muted">{{ isset($actuals[$category->id]) && $actuals[$category->id] ? $n($actuals[$category->id]) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td>Total</td><td class="tb-r">{{ $n($sum($type)) }}</td><td class="tb-r">{{ $n(collect($this->categories[$type] ?? [])->sum(fn ($c) => $actuals[$c->id] ?? 0)) }}</td></tr></tfoot>
                </table>
            </div>
        @endforeach
    </div>

    <style>
        .tb-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1rem; }
        .tb-term { min-width: 16rem; }
        .tb-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .tb-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .tb-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .9rem; }
        .tb-tile { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: .9rem 1.1rem; }
        .tb-tile span { display: block; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        .tb-tile strong { display: block; font-size: 1.3rem; font-weight: 800; margin-top: .15rem; font-variant-numeric: tabular-nums; }
        .tb-in { color: #15803d; }
        .tb-out { color: #b91c1c; }
        .tb-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1100px) { .tb-grid { grid-template-columns: 1fr 1fr; align-items: start; } }
        .tb-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .tb-head { padding: .8rem 1.1rem; font-weight: 700; color: #16233a; border-bottom: 1px solid #eef2f7; }
        .tb-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
        .tb-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; background: #f8fafc; padding: .55rem .9rem; border-bottom: 1px solid #e4e8f0; }
        .tb-table td { padding: .4rem .9rem; border-bottom: 1px solid #f1f5f9; }
        .tb-table tfoot td { font-weight: 700; background: #f8fafc; }
        .tb-r { text-align: right !important; font-variant-numeric: tabular-nums; }
        .tb-muted { color: #64748b; }
        .tb-auto { display: inline-block; margin-left: .4rem; font-size: .68rem; padding: .05rem .4rem; border-radius: 999px; background: #eef4fb; color: #1a5fa8; }
        .tb-input { width: 9rem; text-align: right; padding: .3rem .5rem; border: 1px solid #cbd5e1; border-radius: 7px; font-variant-numeric: tabular-nums; }
        .tb-input:focus { outline: 2px solid #2472c4; border-color: #2472c4; }
    </style>
</x-filament-panels::page>
