@php
    $class = $this->schoolClass;
    $students = $this->students;
    $aLevel = $this->isALevel();
    $optional = $this->optional;
    $range = $this->electiveRange();
@endphp

<x-filament-panels::page>
    <div class="sc-pickers">
        <div>
            <label class="sc-label">Class</label>
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
            <label class="sc-label">Stream</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="sectionId" :disabled="! $this->classId">
                    @if ($this->canSeeWholeClass())<option value="">Whole class</option>@endif
                    @foreach ($this->sectionOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    @if (! $class)
        <div class="sc-empty">Choose a class to record which subjects each learner takes.</div>
    @elseif ($students->isEmpty())
        <div class="sc-empty">No active learners in {{ $class->name }}{{ $this->sectionId ? ' ' . ($this->sectionOptions()[$this->sectionId] ?? '') : '' }}.</div>
    @elseif (! $aLevel && $optional->isEmpty())
        <div class="sc-empty">Every subject in {{ $class->name }} is compulsory, so there is nothing to choose. Mark subjects as optional under Academics → Classes → Subjects.</div>
    @else
        <div class="sc-card">
            <div class="sc-head">
                <div>
                    <strong>{{ $class->name }} — {{ $aLevel ? 'combinations' : 'elective subjects' }}</strong>
                    <div class="sc-muted">
                        @if ($aLevel)
                            Each learner's combination decides which principal subjects count for points; their mark sheets list only learners taking them.
                        @else
                            Everyone takes: {{ $this->compulsory()->map->label()->implode(', ') ?: '—' }}.
                            Tick the extra subjects each learner takes{{ $range ? ' (' . $range['min'] . '–' . $range['max'] . ' each)' : '' }}. Only ticked learners appear on those mark sheets.
                        @endif
                    </div>
                </div>
                <x-filament::button icon="heroicon-o-check" wire:click="save">Save choices</x-filament::button>
            </div>

            @if ($aLevel)
                <div class="sc-bulk">
                    <x-filament::input.wrapper class="sc-bulk-select">
                        <x-filament::input.select wire:model="bulkCombination">
                            <option value="">Combination…</option>
                            @foreach ($this->combinations as $c)
                                <option value="{{ $c->id }}">{{ $c->label() }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" size="sm" wire:click="applyCombination(true)">Give to learners without one</x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="applyCombination(false)">Give to everyone shown</x-filament::button>
                    @php $counts = collect($this->combos)->filter()->countBy(); $none = collect($this->combos)->filter(fn ($c) => ! $c)->count(); @endphp
                    <div class="sc-chips">
                        @foreach ($counts as $cid => $n)
                            <span class="sc-chip">{{ $this->combinations->firstWhere('id', $cid)?->name }} <b>{{ $n }}</b></span>
                        @endforeach
                        @if ($none)<span class="sc-chip sc-chip-warn">No combination <b>{{ $none }}</b></span>@endif
                    </div>
                </div>

                @foreach ($this->combinationGaps() as $combo => $missing)
                    <div class="sc-warn">{{ $combo }} includes {{ implode(', ', $missing) }}, which {{ count($missing) === 1 ? 'is' : 'are' }} not set up for {{ $class->name }} — add {{ count($missing) === 1 ? 'it' : 'them' }} to the class's subjects so marks can be entered.</div>
                @endforeach

                <div class="sc-scroll">
                    <table class="sc-table">
                        <thead>
                            <tr>
                                <th>Learner</th>
                                <th>Combination</th>
                                <th>Subsidiary</th>
                                <th>Subjects taken</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $s)
                                @php
                                    $combo = $this->combinations->firstWhere('id', $this->combos[$s->id] ?? null);
                                    $sub = $this->subsidiaryOptions()->firstWhere('id', $this->subs[$s->id] ?? null) ?? $combo?->subsidiary;
                                @endphp
                                <tr wire:key="al-{{ $s->id }}" @class(['sc-missing' => ! $combo])>
                                    <td>
                                        <div class="sc-name">{{ $s->name ?: 'No name' }}</div>
                                        <div class="sc-muted">{{ $s->admission_no }}{{ $s->section ? ' · ' . $s->section->name : '' }}</div>
                                    </td>
                                    <td>
                                        <select class="sc-input" wire:model.live="combos.{{ $s->id }}">
                                            <option value="">— Choose —</option>
                                            @foreach ($this->combinations as $c)
                                                <option value="{{ $c->id }}">{{ $c->name }}{{ $c->description ? ' — ' . $c->description : '' }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="sc-input" wire:model.live="subs.{{ $s->id }}" @disabled(! $combo)>
                                            <option value="">{{ $combo?->subsidiary ? 'As combination (' . $combo->subsidiary->label() . ')' : 'As combination' }}</option>
                                            @foreach ($this->subsidiaryOptions() as $opt)
                                                @continue($combo && $opt->id === $combo->subsidiary_subject_id)
                                                <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="sc-muted">
                                        @if ($combo)
                                            {{ $combo->subjects->map->label()->implode(', ') }}{{ $sub ? ' + ' . $sub->label() : '' }} + GP
                                        @else
                                            <span class="sc-warn-text">Choose a combination</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="sc-scroll">
                    <table class="sc-table sc-grid">
                        <thead>
                            <tr>
                                <th class="sc-sticky">Learner</th>
                                @foreach ($optional as $subject)
                                    @php $taking = collect($this->picks)->filter(fn ($p) => ! empty($p[$subject->id]))->count(); @endphp
                                    <th class="sc-c" title="{{ $subject->name }}">
                                        <div>{{ $subject->label() }}</div>
                                        <div class="sc-count">{{ $taking }}</div>
                                        <div class="sc-colbtns">
                                            <button type="button" wire:click="setColumn({{ $subject->id }}, true)" title="Tick for everyone shown">all</button>
                                            <button type="button" wire:click="setColumn({{ $subject->id }}, false)" title="Clear for everyone shown">none</button>
                                        </div>
                                    </th>
                                @endforeach
                                <th class="sc-c">Chosen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $s)
                                @php
                                    $n = collect($this->picks[$s->id] ?? [])->filter()->count();
                                    $off = $range && ($n < $range['min'] || $n > $range['max']);
                                @endphp
                                <tr wire:key="ol-{{ $s->id }}">
                                    <td class="sc-sticky">
                                        <div class="sc-name">{{ $s->name ?: 'No name' }}</div>
                                        <div class="sc-muted">{{ $s->admission_no }}{{ $s->section ? ' · ' . $s->section->name : '' }}</div>
                                    </td>
                                    @foreach ($optional as $subject)
                                        <td class="sc-c">
                                            <input type="checkbox" class="sc-check" wire:model.live="picks.{{ $s->id }}.{{ $subject->id }}" aria-label="{{ $s->name }} takes {{ $subject->name }}">
                                        </td>
                                    @endforeach
                                    <td class="sc-c"><span @class(['sc-n', 'sc-n-off' => $off])>{{ $n }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="sc-foot">
                <span class="sc-muted">{{ $students->count() }} learners. Changes are kept only after you click Save choices.</span>
                <x-filament::button icon="heroicon-o-check" wire:click="save">Save choices</x-filament::button>
            </div>
        </div>
    @endif

    <style>
        .sc-pickers { display: grid; grid-template-columns: repeat(2, minmax(10rem, 1fr)); gap: .9rem; max-width: 32rem; }
        .sc-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .sc-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .sc-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .sc-head, .sc-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.2rem; color: #16233a; }
        .sc-head { border-bottom: 1px solid #eef2f7; }
        .sc-foot { border-top: 1px solid #eef2f7; }
        .sc-muted { color: #64748b; font-size: .78rem; line-height: 1.45; }
        .sc-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .8rem 1.2rem; border-bottom: 1px solid #eef2f7; background: #fafcff; }
        .sc-bulk-select { min-width: 14rem; }
        .sc-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-left: auto; }
        .sc-chip { font-size: .74rem; background: #eef4fb; color: #1a5fa8; padding: .2rem .55rem; border-radius: 999px; }
        .sc-chip-warn { background: #fef3c7; color: #92400e; }
        .sc-warn { margin: .6rem 1.2rem 0; padding: .55rem .8rem; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: .8rem; }
        .sc-warn-text { color: #b45309; font-weight: 600; }
        .sc-scroll { overflow-x: auto; }
        .sc-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
        .sc-table th { text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; background: #f8fafc; padding: .55rem .6rem; border-bottom: 1px solid #e4e8f0; white-space: nowrap; vertical-align: bottom; }
        .sc-table td { padding: .45rem .6rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .sc-table tbody tr:hover td { background: #f8fbff; }
        .sc-c { text-align: center !important; }
        .sc-sticky { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 13rem; }
        thead .sc-sticky { background: #f8fafc; }
        .sc-name { font-weight: 600; color: #16233a; white-space: nowrap; }
        .sc-count { font-size: .8rem; font-weight: 800; color: #1a5fa8; text-transform: none; }
        .sc-colbtns { display: flex; gap: .25rem; justify-content: center; margin-top: .2rem; }
        .sc-colbtns button { font-size: .62rem; text-transform: none; letter-spacing: 0; color: #1a5fa8; border: 1px solid #d6e4f5; border-radius: 4px; padding: 0 .3rem; background: #fff; }
        .sc-colbtns button:hover { background: #eef4fb; }
        .sc-check { width: 1.05rem; height: 1.05rem; accent-color: #1a5fa8; cursor: pointer; }
        .sc-n { display: inline-block; min-width: 1.6rem; padding: .1rem .4rem; border-radius: 999px; font-weight: 700; background: #dcfce7; color: #166534; }
        .sc-n-off { background: #fee2e2; color: #991b1b; }
        .sc-input { width: 100%; min-width: 11rem; font-size: .8rem; padding: .35rem .5rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; }
        .sc-input:disabled { background: #f8fafc; color: #94a3b8; }
        .sc-missing td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
    </style>
</x-filament-panels::page>
