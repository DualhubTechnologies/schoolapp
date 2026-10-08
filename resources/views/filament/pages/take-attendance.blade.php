@php
    $students = $this->students;
    $day = $this->day();
    $counts = collect($this->statuses)->countBy();
    $labels = \App\Models\AttendanceRecord::STATUSES;
    $short = \App\Models\AttendanceRecord::SHORT;
@endphp

<x-filament-panels::page>
    <div class="at-pickers">
        <div>
            <label class="at-label">Day</label>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="date" max="{{ now()->toDateString() }}" />
            </x-filament::input.wrapper>
        </div>
        <div>
            <label class="at-label">Class</label>
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
            <label class="at-label">Stream</label>
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

    @if ($this->classOptions()->isEmpty())
        <div class="at-empty">You are not class teacher of any stream. Ask the administrator to set you as class teacher under <strong>Academics → Streams</strong>.</div>
    @elseif (! $this->classId)
        <div class="at-empty">Choose a class to take its register.</div>
    @elseif ($students->isEmpty())
        <div class="at-empty">No active learners here{{ $this->mustPickStream() ? ' — choose your stream' : '' }}.</div>
    @else
        <form wire:submit="save" class="at-card">
            <div class="at-head">
                <div>
                    <div class="at-title">{{ $day->format('l j F Y') }}</div>
                    <div class="at-muted">
                        {{ $students->count() }} learners ·
                        <strong>{{ ($counts['present'] ?? 0) + ($counts['late'] ?? 0) }}</strong> present ·
                        <strong>{{ $counts['absent'] ?? 0 }}</strong> absent
                        @if ($counts['excused'] ?? 0) · <strong>{{ $counts['excused'] }}</strong> excused @endif
                        · {{ $this->alreadyTaken ? 'Register saved — you can still correct it' : 'Not saved yet' }}
                    </div>
                </div>
                <div class="at-head-actions">
                    <x-filament::button type="button" size="sm" color="gray" wire:click="markAll('present')">All present</x-filament::button>
                    {{ $this->textAbsentParentsAction }}
                </div>
            </div>

            <div class="at-list">
                @foreach ($students as $i => $student)
                    @php($id = $student->id)
                    <div class="at-row" wire:key="at-{{ $id }}">
                        <div class="at-who">
                            <span class="at-n">{{ $i + 1 }}</span>
                            <div>
                                <div class="at-name">{{ $student->name }}</div>
                                <div class="at-muted">{{ $student->admission_no }}</div>
                            </div>
                        </div>
                        <div class="at-choices" role="radiogroup" aria-label="{{ $student->name }}">
                            @foreach ($labels as $status => $label)
                                <label class="at-choice is-{{ $status }}" title="{{ $label }}">
                                    <input type="radio" wire:model.live="statuses.{{ $id }}" value="{{ $status }}">
                                    <span>{{ $short[$status] }}<em>{{ $label }}</em></span>
                                </label>
                            @endforeach
                        </div>
                        @if (in_array($this->statuses[$id] ?? 'present', ['absent', 'excused', 'late'], true))
                            <input type="text" class="at-note" wire:model="notes.{{ $id }}" placeholder="Reason (optional)" maxlength="255">
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="at-foot">
                <span class="at-muted">P present · A absent · L late (counts as present) · E excused</span>
                <x-filament::button type="submit" icon="heroicon-o-check">Save register</x-filament::button>
            </div>
        </form>
    @endif

    <style>
        .at-pickers { display: grid; grid-template-columns: 1fr; gap: .9rem; }
        @media (min-width: 768px) { .at-pickers { grid-template-columns: 1fr 1fr 1fr; max-width: 48rem; } }
        .at-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .at-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .at-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .at-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem 1rem; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #eef2f7; }
        .at-head-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
        .at-title { font-weight: 700; color: #16233a; font-size: 1.02rem; }
        .at-muted { color: #64748b; font-size: .8rem; }
        .at-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem 1rem; padding: .55rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
        .at-who { display: flex; align-items: center; gap: .75rem; min-width: 12rem; }
        .at-n { width: 1.5rem; color: #94a3b8; font-size: .8rem; }
        .at-name { font-weight: 600; color: #16233a; }
        .at-choices { display: flex; gap: .35rem; }
        .at-choice input { position: absolute; opacity: 0; pointer-events: none; }
        .at-choice span { display: inline-flex; align-items: center; justify-content: center; min-width: 2.6rem; height: 2.4rem; padding: 0 .6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #64748b; cursor: pointer; user-select: none; }
        .at-choice em { font-style: normal; font-weight: 600; margin-left: .35rem; display: none; }
        @media (min-width: 900px) { .at-choice em { display: inline; } }
        .at-choice input:focus-visible + span { outline: 2px solid #2472c4; outline-offset: 1px; }
        .at-choice.is-present input:checked + span { background: #dcfce7; border-color: #16a34a; color: #166534; }
        .at-choice.is-absent input:checked + span { background: #fee2e2; border-color: #dc2626; color: #991b1b; }
        .at-choice.is-late input:checked + span { background: #fef3c7; border-color: #d97706; color: #92400e; }
        .at-choice.is-excused input:checked + span { background: #e0ecfb; border-color: #2472c4; color: #1a5fa8; }
        .at-note { flex-basis: 100%; padding: .35rem .6rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: .82rem; }
        @media (min-width: 900px) { .at-note { flex-basis: 16rem; } }
        .at-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem 1rem; padding: .85rem 1.25rem; background: #fafbfd; position: sticky; bottom: 0; border-top: 1px solid #eef2f7; }
    </style>
</x-filament-panels::page>
