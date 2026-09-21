@php
    $r = $this->results;
    $isHead = \App\Support\AcademicAccess::manages();
    $overall = fn ($row) => match ($r['curriculum'] ?? null) {
        'primary' => $row['division'] ? ($row['aggregate'] !== null ? "Agg. {$row['aggregate']} · {$row['division']}" : 'Incomplete') : '—',
        'a_level' => $row['points'] !== null ? "{$row['points']} points · {$row['result_code']}" : '—',
        default => $row['overall_grade'] ? "{$row['overall_grade']} · {$row['overall_descriptor']}" : '—',
    };
@endphp

<x-filament-panels::page>
    <div class="rc-bar">
        <div class="rc-pickers">
            <div>
                <label class="rc-label">Term</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="termId">
                        @foreach ($this->termOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="rc-label">Class</label>
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
                <label class="rc-label">Stream</label>
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
    </div>

    @if (! $r)
        <div class="rc-empty">Choose a term and class.</div>
    @elseif (! $r['summary']['with_results'])
        <div class="rc-empty">No marks entered for {{ $r['class']->name }} in {{ $r['term']->label() }} yet.</div>
    @else
        <div class="rc-card">
            <div class="rc-head">
                <div>
                    <strong>{{ $r['class']->name }} — {{ $r['term']->label() }}</strong>
                    <div class="rc-muted">{{ $r['summary']['with_results'] }} students with results. Write or suggest comments, save, then print.</div>
                </div>
                <div class="rc-actions">
                    <label class="rc-check"><input type="checkbox" wire:model.live="showFees"> Show fees balance</label>
                    <x-filament::button color="gray" icon="heroicon-o-sparkles" wire:click="fillComments">Fill empty comments</x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-check" wire:click="saveComments">Save comments</x-filament::button>
                    <x-filament::button icon="heroicon-o-printer" tag="a" :href="$this->printUrl()" target="_blank">Print all</x-filament::button>
                </div>
            </div>

            <div class="rc-scroll">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th class="rc-c">Pos.</th>
                            <th>Student</th>
                            <th class="rc-c">Avg.</th>
                            <th>Result</th>
                            <th>Class teacher's comment</th>
                            <th>Head teacher's comment</th>
                            <th>Conduct</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($r['rows'] as $row)
                            @php($id = $row['student']->id)
                            <tr wire:key="rc-{{ $id }}">
                                <td class="rc-c rc-pos">{{ $row['position'] ?? '—' }}</td>
                                <td>
                                    <div class="rc-name">{{ $row['student']->name ?: 'No name' }}</div>
                                    <div class="rc-muted">{{ $row['student']->admission_no }}{{ $row['student']->section ? ' · ' . $row['student']->section->name : '' }}</div>
                                </td>
                                <td class="rc-c rc-strong">{{ $row['average'] ?? '—' }}</td>
                                <td class="rc-nowrap">{{ $overall($row) }}</td>
                                <td><textarea rows="2" class="rc-input" wire:model="comments.{{ $id }}.class_teacher_comment" placeholder="Class teacher's comment"></textarea></td>
                                <td><textarea rows="2" class="rc-input" wire:model="comments.{{ $id }}.head_teacher_comment" placeholder="{{ $isHead ? 'Head teacher\'s comment' : 'Head teacher only' }}" @disabled(! $isHead)></textarea></td>
                                <td>
                                    <select class="rc-input" wire:model="comments.{{ $id }}.conduct">
                                        <option value="">—</option>
                                        @foreach (\App\Filament\Pages\ReportCards::CONDUCT as $c)
                                            <option value="{{ $c }}">{{ $c }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><a class="rc-link" href="{{ $this->printUrl($id) }}" target="_blank">Print</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <style>
        .rc-pickers { display: grid; grid-template-columns: repeat(3, minmax(10rem, 1fr)); gap: .9rem; max-width: 48rem; }
        .rc-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .rc-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .rc-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .rc-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1rem 1.2rem; border-bottom: 1px solid #eef2f7; color: #16233a; }
        .rc-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .rc-check { display: flex; align-items: center; gap: .35rem; font-size: .82rem; color: #374151; margin-right: .5rem; }
        .rc-muted { color: #64748b; font-size: .78rem; }
        .rc-scroll { overflow-x: auto; }
        .rc-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
        .rc-table th { text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; background: #f8fafc; padding: .55rem .6rem; border-bottom: 1px solid #e4e8f0; white-space: nowrap; }
        .rc-table td { padding: .5rem .6rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .rc-c { text-align: center !important; }
        .rc-pos { font-weight: 800; }
        .rc-name { font-weight: 600; color: #16233a; white-space: nowrap; }
        .rc-strong { font-weight: 700; }
        .rc-nowrap { white-space: nowrap; }
        .rc-input { width: 100%; min-width: 11rem; font-size: .8rem; padding: .35rem .5rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; }
        .rc-input:disabled { background: #f8fafc; }
        .rc-link { color: #1a5fa8; font-weight: 600; font-size: .8rem; }
    </style>
</x-filament-panels::page>
