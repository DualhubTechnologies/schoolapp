@php
    $class = $this->schoolClass;
    $subject = $this->subject;
    $topics = $this->topics;
    $students = $subject ? $this->students() : collect();
    $term = $this->term();
    $levels = \App\Models\TopicScore::LEVELS;
@endphp

<x-filament-panels::page>
    <div class="at-pickers">
        <div>
            <label class="at-label" for="at-class">Class</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="at-class" wire:model.live="classId">
                    <option value="">Choose…</option>
                    @foreach ($this->classOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div>
            <label class="at-label" for="at-subject">Subject</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="at-subject" wire:model.live="subjectId" :disabled="! $class">
                    <option value="">Choose…</option>
                    @foreach ($this->subjectOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div class="at-term">
            <span class="at-label">Term</span>
            <strong>{{ $term?->label() ?? 'No current term' }}</strong>
        </div>
    </div>

    @if ($this->classOptions()->isEmpty())
        <div class="at-empty">Topic assessment is for O-Level classes (new lower-secondary curriculum). None of your classes follow it.</div>
    @elseif (! $subject)
        <div class="at-empty">Choose a class and a subject to record each learner's level in its syllabus topics.</div>
    @elseif ($topics->isEmpty())
        <div class="at-empty">
            {{ $subject->name }} has no syllabus topics for {{ $class?->name }} yet.
            <x-filament::link :href="$this->topicsUrl()">Add them under Syllabus topics</x-filament::link>, then come back.
        </div>
    @else
        <form wire:submit="save" class="at-card">
            <div class="at-head">
                <div>
                    <div class="at-title">{{ $subject->name }} · {{ $class?->name }}</div>
                    <div class="at-muted">{{ $students->count() }} {{ str('learner')->plural($students->count()) }} · {{ $topics->count() }} {{ str('topic')->plural($topics->count()) }}. Leave a box blank for a topic not assessed yet.</div>
                </div>
                <div class="at-key">
                    @foreach ($levels as $level => $meaning)
                        <span><i class="at-lv at-l{{ $level }}">{{ $level }}</i>{{ $meaning }}</span>
                    @endforeach
                </div>
            </div>

            <div class="at-scroll">
                <table class="at-table">
                    <thead>
                        <tr>
                            <th>Learner</th>
                            @foreach ($topics as $topic)
                                <th class="at-c" title="{{ $topic->label() }}">{{ $topic->shortLabel() }}<span class="at-topic">{{ $topic->name }}</span></th>
                            @endforeach
                            <th class="at-c">Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php
                                $chosen = collect($this->levels[$student->id] ?? [])->filter(fn ($v) => $v !== null && $v !== '');
                            @endphp
                            <tr wire:key="at-{{ $student->id }}">
                                <td class="at-name">{{ $student->name }}<span class="at-muted">{{ $student->admission_no }}</span></td>
                                @foreach ($topics as $topic)
                                    @php $value = $this->levels[$student->id][$topic->id] ?? null; @endphp
                                    <td class="at-c">
                                        <select
                                            id="lv-{{ $student->id }}-{{ $topic->id }}"
                                            aria-label="{{ $student->name }}, {{ $topic->label() }}"
                                            wire:model.live="levels.{{ $student->id }}.{{ $topic->id }}"
                                            @class(['at-select', 'at-l'.$value => $value !== null && $value !== ''])
                                        >
                                            <option value="">–</option>
                                            @foreach ([3, 2, 1, 0] as $level)
                                                <option value="{{ $level }}">{{ $level }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td class="at-c at-avg">{{ $chosen->isNotEmpty() ? number_format($chosen->avg(), 1) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="at-foot">
                <span class="at-muted">The average (out of 3) is the learner's Topic assessment mark for {{ $term?->label() }}.</span>
                <x-filament::button type="submit" icon="heroicon-m-check">Save levels</x-filament::button>
            </div>
        </form>
    @endif

    <style>
        .at-pickers { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: .9rem; align-items: end; }
        @media (min-width: 768px) { .at-pickers { grid-template-columns: 1fr 2fr 1fr; } }
        .at-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .at-term strong { display: block; padding: .5rem 0; color: #16233a; }
        .at-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .at-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .at-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem 1.5rem; padding: 1rem 1.25rem; border-bottom: 1px solid #eef2f7; }
        .at-title { font-weight: 700; color: #16233a; font-size: 1.02rem; }
        .at-muted { color: #64748b; font-size: .78rem; display: block; font-weight: 400; }
        .at-key { display: grid; gap: .2rem; font-size: .75rem; color: #374151; }
        .at-key span { display: flex; align-items: center; gap: .4rem; }
        .at-lv { display: inline-grid; place-items: center; width: 1.2rem; height: 1.2rem; border-radius: 4px; color: #fff; font-style: normal; font-weight: 700; font-size: .7rem; }
        .at-scroll { overflow-x: auto; }
        .at-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .at-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: .6rem .5rem; background: #f8fafc; border-bottom: 1px solid #e4e8f0; vertical-align: bottom; }
        .at-table td { padding: .4rem .5rem; border-bottom: 1px solid #f1f5f9; }
        .at-c { text-align: center !important; }
        .at-topic { display: block; max-width: 7rem; margin: .15rem auto 0; font-size: .65rem; text-transform: none; letter-spacing: 0; font-weight: 400; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .at-name { font-weight: 600; color: #16233a; white-space: nowrap; }
        .at-select { width: 3.3rem; padding: .3rem .2rem; border: 1px solid #cbd5e1; border-radius: 7px; text-align: center; font-weight: 700; background: #fff; }
        .at-select:focus { outline: 2px solid #2472c4; border-color: #2472c4; }
        .at-l0 { background: #c2410c; color: #fff; border-color: #c2410c; }
        .at-l1 { background: #d97706; color: #fff; border-color: #d97706; }
        .at-l2 { background: #65a30d; color: #fff; border-color: #65a30d; }
        .at-l3 { background: #15803d; color: #fff; border-color: #15803d; }
        .at-avg { font-weight: 700; color: #1a5fa8; }
        .at-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem 1rem; padding: .85rem 1.25rem; border-top: 1px solid #eef2f7; background: #fafbfd; position: sticky; bottom: 0; }
    </style>
</x-filament-panels::page>
