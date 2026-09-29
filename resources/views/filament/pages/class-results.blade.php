@php
    $r = $this->results;
    $subjects = $r ? $r['subjects']->filter(fn ($s) => isset($r['subject_stats'][$s->id])) : collect();
    $overall = $r ? $this->overallColumns($r['curriculum']) : [];
@endphp

<x-filament-panels::page>
    <div class="cr-bar cr-no-print">
        <div class="cr-pickers">
            <div>
                <label class="cr-label">Term</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="termId">
                        @foreach ($this->termOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="cr-label">Results for</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="examId">
                        <option value="">Whole term</option>
                        @foreach ($this->examOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }} only</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="cr-label">Class</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="classId">
                        <option value="">Choose a class…</option>
                        @foreach ($this->classOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="cr-label">Stream</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sectionId" :disabled="! $this->classId">
                        <option value="">Whole class</option>
                        @foreach ($this->sectionOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
        @if ($r && $r['summary']['with_results'])
            <div class="cr-actions">
                <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportCsv">Excel (CSV)</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-printer" onclick="window.print()">Print</x-filament::button>
                <x-filament::button icon="heroicon-o-document-text" tag="a" :href="\App\Filament\Pages\ReportCards::getUrl(['term' => $this->termId, 'class' => $this->classId])">Report cards</x-filament::button>
            </div>
        @endif
    </div>

    @if (! $r)
        <div class="cr-empty">Choose a term and class to see results.</div>
    @elseif (! $r['summary']['with_results'])
        <div class="cr-empty">No marks entered for {{ $r['class']->name }} in {{ $r['term']->label() }} yet. Enter them under <strong>Exams &amp; Results → Enter Marks</strong>.</div>
    @else
        {{-- ── Summary ── --}}
        <div class="cr-tiles">
            <div class="cr-tile"><span>Students with results</span><strong>{{ $r['summary']['with_results'] }} / {{ $r['summary']['students'] }}</strong></div>
            <div class="cr-tile"><span>Class average</span><strong>{{ $r['summary']['class_average'] }}%</strong></div>
            <div class="cr-tile cr-wide">
                <span>{{ ['primary' => 'Divisions', 'a_level' => 'Points'][$r['curriculum']] ?? 'Achievement levels' }}</span>
                <div class="cr-dist">
                    @foreach ($r['summary']['distribution'] as $label => $count)
                        <span class="cr-chip">{{ $label }} <b>{{ $count }}</b></span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Broadsheet ── --}}
        <div class="cr-card">
            <div class="cr-card-head">
                <strong>{{ $r['class']->name }}{{ $this->sectionId ? ' ' . $this->sectionOptions()[$this->sectionId] : '' }} — {{ $r['term']->label() }}</strong>
                <span class="cr-muted">Term scores (%) with grades. Positions are in the whole class; stream positions in brackets.</span>
            </div>
            <div class="cr-scroll">
                <table class="cr-table">
                    <thead>
                        <tr>
                            <th class="cr-c">Pos.</th>
                            <th>Student</th>
                            @foreach ($subjects as $subject)
                                <th class="cr-c" title="{{ $subject->name }}">{{ $subject->label() }}</th>
                            @endforeach
                            <th class="cr-c">Total</th>
                            <th class="cr-c">Avg.</th>
                            @foreach ($overall as $heading)
                                <th class="cr-c">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['rows'] as $row)
                            <tr>
                                <td class="cr-c cr-pos">
                                    {{ $row['position'] ?? '—' }}
                                    @if ($row['stream_position'] && $row['student']->section)<span class="cr-muted">({{ $row['stream_position'] }})</span>@endif
                                </td>
                                <td>
                                    <div class="cr-name">{{ $row['student']->name ?: 'No name' }}</div>
                                    <div class="cr-muted">{{ $row['student']->admission_no }}{{ $row['student']->section ? ' · ' . $row['student']->section->name : '' }}{{ $row['student']->combination ? ' · ' . $row['student']->combination->name : '' }}</div>
                                </td>
                                @foreach ($subjects as $subject)
                                    @php($res = $row['subjects'][$subject->id] ?? null)
                                    <td class="cr-c">
                                        @if ($res && $res['final'] !== null)
                                            <div class="cr-score">{{ $res['final'] + 0 }}</div>
                                            <div class="cr-grade">{{ $res['grade'] }}</div>
                                        @elseif ($res)
                                            <span class="cr-muted">AB</span>
                                        @else
                                            <span class="cr-muted">·</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="cr-c">{{ $row['total'] + 0 }}</td>
                                <td class="cr-c cr-strong">{{ $row['average'] ?? '—' }}</td>
                                @foreach (array_keys($overall) as $key)
                                    <td class="cr-c cr-strong">{{ $row[$key] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Subject analysis ── --}}
        <div class="cr-card">
            <div class="cr-card-head"><strong>Subject analysis</strong></div>
            <div class="cr-scroll">
                <table class="cr-table">
                    <thead>
                        <tr><th>Subject</th><th class="cr-c">Students</th><th class="cr-c">Average</th><th class="cr-c">Highest</th><th class="cr-c">Lowest</th><th>Grades</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($subjects->sortByDesc(fn ($s) => $r['subject_stats'][$s->id]['average']) as $subject)
                            @php($st = $r['subject_stats'][$subject->id])
                            <tr>
                                <td class="cr-name">{{ $subject->name }}</td>
                                <td class="cr-c">{{ $st['count'] }}</td>
                                <td class="cr-c cr-strong">{{ $st['average'] }}%</td>
                                <td class="cr-c">{{ $st['highest'] + 0 }}</td>
                                <td class="cr-c">{{ $st['lowest'] + 0 }}</td>
                                <td>
                                    @foreach ($st['grades'] as $grade => $count)
                                        <span class="cr-chip">{{ $grade }} <b>{{ $count }}</b></span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <style>
        .cr-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1rem; }
        .cr-pickers { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .9rem; flex: 1; max-width: 64rem; }
        .cr-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .cr-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .cr-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .cr-muted { color: #64748b; font-size: .75rem; }
        .cr-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .9rem; }
        @media (min-width: 900px) { .cr-tiles { grid-template-columns: 1fr 1fr 2fr; } }
        .cr-tile { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: .9rem 1.1rem; }
        .cr-tile span { display: block; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        .cr-tile strong { display: block; font-size: 1.35rem; font-weight: 800; color: #16233a; margin-top: .15rem; }
        .cr-dist { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .45rem; }
        .cr-chip { display: inline-block; font-size: .75rem; padding: .15rem .5rem; border-radius: 999px; background: #eef4fb; color: #1e3a5f; margin: 0 .2rem .2rem 0; }
        .cr-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .cr-card-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; padding: .85rem 1.1rem; border-bottom: 1px solid #eef2f7; color: #16233a; }
        .cr-scroll { overflow-x: auto; }
        .cr-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .cr-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; background: #f8fafc; padding: .55rem .6rem; border-bottom: 1px solid #e4e8f0; text-align: left; white-space: nowrap; }
        .cr-table td { padding: .45rem .6rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .cr-table tbody tr:nth-child(even) td { background: #fbfcfe; }
        .cr-c { text-align: center !important; }
        .cr-name { font-weight: 600; color: #16233a; white-space: nowrap; }
        .cr-score { font-weight: 600; font-variant-numeric: tabular-nums; }
        .cr-grade { font-size: .7rem; font-weight: 700; color: #1a5fa8; }
        .cr-pos { font-weight: 800; color: #16233a; white-space: nowrap; }
        .cr-strong { font-weight: 700; }
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            .cr-no-print, .fi-sidebar, .fi-topbar, .sh-topbar, .fi-footer { display: none !important; }
            .fi-main, .fi-page, .fi-main-ctn { padding: 0 !important; margin: 0 !important; }
            .cr-scroll { overflow: visible; }
            .cr-table { font-size: 9px; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</x-filament-panels::page>
