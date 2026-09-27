{{-- Transport collections (App\Filament\App\Pages\TransportCollections). --}}
@php
    $s = $this->summary;
    $n = fn ($v) => number_format((float) $v);
    $termLabel = $this->termOptions()[$this->termId] ?? '';
@endphp

<x-filament-panels::page>
    <div class="tc-top tc-no-print">
        <div class="tc-pickers">
            <div>
                <label class="tc-label">Term</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="termId">
                        @foreach ($this->termOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="tc-label">Route</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="routeId">
                        <option value="">All routes</option>
                        @foreach ($this->routeOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="tc-label">Show</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="show">
                        <option value="all">Everyone</option>
                        <option value="owing">Still owing</option>
                        <option value="paid">Fully paid</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
        <div class="tc-actions">
            <x-filament::button color="gray" icon="heroicon-o-clipboard-document-list" tag="a" :href="route('filament.app.transport.route-lists')" target="_blank">Route lists</x-filament::button>
            <x-filament::button color="gray" icon="heroicon-o-printer" onclick="window.print()">Print</x-filament::button>
        </div>
    </div>

    <p class="tc-note tc-no-print">Payments pay for the van first; whatever is left goes to school fees.</p>

    <div class="tc-print-title">{{ auth()->user()->school?->name }} — Transport collections — {{ $termLabel }}</div>

    <div class="tc-tiles">
        <div class="tc-tile"><span>Learners on the van</span><strong>{{ $n($s['total']['learners']) }}</strong></div>
        <div class="tc-tile"><span>Billed</span><strong>UGX {{ $n($s['total']['charged']) }}</strong></div>
        <div class="tc-tile tc-good"><span>Collected</span><strong>UGX {{ $n($s['total']['paid']) }}</strong>
            <em>{{ $s['total']['charged'] > 0 ? round($s['total']['paid'] / $s['total']['charged'] * 100) : 0 }}% of billed</em></div>
        <div @class(['tc-tile', 'tc-bad' => $s['total']['owed'] > 0])><span>Still owing</span><strong>UGX {{ $n($s['total']['owed']) }}</strong></div>
    </div>

    @if ($this->rows->isEmpty())
        <div class="tc-empty">
            No transport billed for this term{{ $this->show !== 'all' ? ' matches this filter' : '' }}.
            Put learners on a route under <strong>Learners on the van</strong>, then bill the term under <strong>Fees → Billing</strong>.
        </div>
    @else
        <div class="tc-card">
            <div class="tc-head">By route</div>
            <table class="tc-table">
                <thead><tr><th>Route</th><th class="tc-r">Learners</th><th class="tc-r">Billed</th><th class="tc-r">Collected</th><th class="tc-r">Owing</th></tr></thead>
                <tbody>
                    @foreach ($s['routes'] as $route => $r)
                        <tr>
                            <td class="tc-strong">{{ $route }}</td>
                            <td class="tc-r">{{ $r['learners'] }}</td>
                            <td class="tc-r">{{ $n($r['charged']) }}</td>
                            <td class="tc-r">{{ $n($r['paid']) }}</td>
                            <td @class(['tc-r', 'tc-owe' => $r['owed'] > 0])>{{ $n($r['owed']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td>Total</td><td class="tc-r">{{ $s['total']['learners'] }}</td><td class="tc-r">{{ $n($s['total']['charged']) }}</td><td class="tc-r">{{ $n($s['total']['paid']) }}</td><td class="tc-r">{{ $n($s['total']['owed']) }}</td></tr>
                </tfoot>
            </table>
        </div>

        <div class="tc-card">
            <div class="tc-head">Learners</div>
            <table class="tc-table">
                <thead><tr><th>Learner</th><th>Class</th><th>Route</th><th>Parent / guardian</th><th class="tc-r">Billed</th><th class="tc-r">Paid</th><th class="tc-r">Owing</th></tr></thead>
                <tbody>
                    @foreach ($this->rows as $row)
                        <tr>
                            <td><span class="tc-strong">{{ $row['student']->name }}</span><br><span class="tc-muted">{{ $row['student']->admission_no }}</span></td>
                            <td>{{ $row['student']->schoolClass?->name }}</td>
                            <td>{{ $row['route'] }}</td>
                            <td>{{ $row['student']->guardian?->name ?? '—' }}<br><span class="tc-muted">{{ $row['student']->guardian?->phone }}</span></td>
                            <td class="tc-r">{{ $n($row['charged']) }}</td>
                            <td class="tc-r">{{ $n($row['paid']) }}</td>
                            <td class="tc-r">
                                @if ($row['owed'] > 0)
                                    <span class="tc-pill tc-pill-bad">{{ $n($row['owed']) }}</span>
                                @else
                                    <span class="tc-pill tc-pill-good">Paid</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <style>
        .tc-top { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
        .tc-pickers, .tc-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .tc-pickers > div { min-width: 12rem; }
        .tc-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .tc-note { margin-top: -.25rem; font-size: .85rem; color: #6b7280; }
        .tc-print-title { display: none; font-weight: 700; font-size: 1.1rem; }
        .tc-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
        .tc-tile { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: .9rem 1rem; }
        .tc-tile span { display: block; font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
        .tc-tile strong { display: block; margin-top: .25rem; font-size: 1.35rem; font-weight: 800; color: #111827; }
        .tc-tile em { display: block; font-style: normal; font-size: .8rem; color: #6b7280; }
        .tc-good strong { color: #15803d; }
        .tc-bad strong { color: #b91c1c; }
        .tc-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .tc-head { padding: .75rem 1rem; font-weight: 700; border-bottom: 1px solid #eef1f6; }
        .tc-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .tc-table th { text-align: left; padding: .6rem 1rem; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; background: #f8fafc; }
        .tc-table td { padding: .6rem 1rem; border-top: 1px solid #f1f4f9; vertical-align: top; }
        .tc-table tfoot td { font-weight: 700; background: #f8fafc; }
        .tc-r { text-align: right; white-space: nowrap; }
        .tc-strong { font-weight: 600; }
        .tc-muted { color: #6b7280; font-size: .82rem; }
        .tc-owe { color: #b91c1c; font-weight: 600; }
        .tc-pill { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 600; }
        .tc-pill-bad { background: #fef2f2; color: #b91c1c; }
        .tc-pill-good { background: #f0fdf4; color: #15803d; }
        .tc-empty { padding: 2rem; text-align: center; color: #6b7280; background: #fff; border: 1px dashed #d8dee9; border-radius: 12px; }
        @media (max-width: 900px) { .tc-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } .tc-card { overflow-x: auto; } }
        @media print {
            .tc-no-print, .fi-sidebar, .fi-topbar, .fi-header { display: none !important; }
            .tc-print-title { display: block; }
            .tc-card, .tc-tile { break-inside: avoid; }
        }
    </style>
</x-filament-panels::page>
