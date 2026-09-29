@php
    $rows = $this->rows;
    $counts = $this->counts();
    $total = $rows->count();
    $done = $counts['approved'];
@endphp

<x-filament-panels::page>
    <div class="mp-pickers">
        <div>
            <label class="mp-label">Exam</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="assessmentId">
                    <option value="">Choose an exam…</option>
                    @foreach ($this->assessmentOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div>
            <label class="mp-label">Class</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="classId">
                    <option value="">All classes</option>
                    @foreach ($this->classOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div class="mp-actions">{{ $this->approveAllAction }}</div>
    </div>

    @if (! $this->assessment)
        <div class="mp-empty">No exams yet. Create one under <strong>Exams &amp; Results → Exams</strong>.</div>
    @elseif ($total === 0)
        <div class="mp-empty">No class subjects for this exam. Give classes their subjects under <strong>Academics → Classes</strong>.</div>
    @else
        <div class="mp-stats">
            <div class="mp-stat is-total"><b>{{ $done }} / {{ $total }}</b><span>sheets approved</span></div>
            @foreach (\App\Filament\Pages\MarksProgress::STATUS_LABELS as $status => $label)
                <div class="mp-stat is-{{ $status }}"><b>{{ $counts[$status] }}</b><span>{{ $label }}</span></div>
            @endforeach
        </div>

        <div class="mp-card">
            <div class="mp-scroll">
                <table class="mp-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th class="mp-c">Entered</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($pct = $row->percent())
                            <tr wire:key="mp-{{ $row->class->id }}-{{ $row->subject->id }}">
                                <td class="mp-strong">{{ $row->class->name }}</td>
                                <td>{{ $row->subject->name }}</td>
                                <td>{!! $row->teacher ? e($row->teacher) : '<span class="mp-muted">No teacher</span>' !!}</td>
                                <td class="mp-c">
                                    <div class="mp-bar"><span style="width: {{ $pct }}%"></span></div>
                                    <span class="mp-muted">{{ $row->entered }} / {{ $row->learners }}</span>
                                </td>
                                <td>
                                    <span class="mp-pill is-{{ $row->status }}">{{ \App\Filament\Pages\MarksProgress::STATUS_LABELS[$row->status] }}</span>
                                    @if ($row->sheet->isOpen() && $row->sheet->returned_note)
                                        <div class="mp-muted">Returned: “{{ $row->sheet->returned_note }}”</div>
                                    @endif
                                </td>
                                <td class="mp-r"><a class="mp-link" href="{{ $row->url }}">Open sheet →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <style>
        .mp-pickers { display: grid; grid-template-columns: 1fr; gap: .9rem; align-items: end; }
        @media (min-width: 768px) { .mp-pickers { grid-template-columns: 2fr 1fr auto; } }
        .mp-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .mp-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .mp-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; }
        @media (min-width: 768px) { .mp-stats { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
        .mp-stat { background: #fff; border: 1px solid #e4e8f0; border-radius: 10px; padding: .6rem .8rem; }
        .mp-stat b { display: block; font-size: 1.25rem; color: #16233a; }
        .mp-stat span { font-size: .75rem; color: #64748b; }
        .mp-stat.is-total { background: #0d1f38; border-color: #0d1f38; }
        .mp-stat.is-total b, .mp-stat.is-total span { color: #fff; }
        .mp-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .mp-scroll { overflow-x: auto; }
        .mp-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .mp-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: .6rem .75rem; background: #f8fafc; border-bottom: 1px solid #e4e8f0; }
        .mp-table td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .mp-c { text-align: center !important; }
        .mp-r { text-align: right; white-space: nowrap; }
        .mp-strong { font-weight: 600; color: #16233a; }
        .mp-muted { color: #64748b; font-size: .78rem; }
        .mp-bar { width: 6rem; height: .4rem; margin: 0 auto .2rem; background: #eef2f7; border-radius: 999px; overflow: hidden; }
        .mp-bar span { display: block; height: 100%; background: #2472c4; }
        .mp-pill { font-size: .72rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; white-space: nowrap; }
        .mp-pill.is-not_started { background: #fee2e2; color: #991b1b; }
        .mp-pill.is-in_progress { background: #e0ecfb; color: #1a5fa8; }
        .mp-pill.is-complete { background: #ede9fe; color: #5b21b6; }
        .mp-pill.is-submitted { background: #fef3c7; color: #92400e; }
        .mp-pill.is-approved { background: #dcfce7; color: #166534; }
        .mp-link { font-weight: 600; color: #1a5fa8; }
    </style>
</x-filament-panels::page>
