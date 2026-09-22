@php
    $class = $this->reviewClass;
    $actions = \App\Models\Promotion::ACTIONS;
    $counts = collect($this->decisions)->countBy()->all();
    $recommended = collect($this->advice)->countBy('recommendation')->all();
    $adviceLabels = \App\Services\Academics\PromotionAdvisor::LABELS;
@endphp

<x-filament-panels::page>
    <div class="ps-intro">
        <div>
            <strong>{{ $this->year?->name ?? 'No current academic year' }}</strong>
            <span class="ps-muted">— promote each class at the end of the year. Work from any class; nobody is moved twice in a year. Fee balances carry forward automatically.</span>
        </div>
    </div>

    @if (! $class)
        {{-- ── Overview of every class ── --}}
        <div class="ps-card">
            <table class="ps-table">
                <thead>
                    <tr><th>Class</th><th>Default</th><th class="ps-c">Still to move</th><th class="ps-c">Moved this year</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($this->overview as $row)
                        <tr>
                            <td class="ps-strong">{{ $row['class']->name }} <span class="ps-muted">{{ config('academics.curricula')[$row['class']->curriculum()] ?? '' }}</span></td>
                            <td>{{ $row['default'] }}</td>
                            <td class="ps-c">{{ $row['pending'] }}</td>
                            <td class="ps-c">{{ $row['done'] ?: '—' }}</td>
                            <td class="ps-r">
                                @if ($row['pending'])
                                    <x-filament::button size="sm" wire:click="review({{ $row['class']->id }})">Review &amp; promote</x-filament::button>
                                @else
                                    <span class="ps-done">✓ Done</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        {{-- ── One class: a decision per student ── --}}
        <div class="ps-card">
            <div class="ps-head">
                <div>
                    <div class="ps-title">{{ $class->name }} → {{ $this->targetClass?->name ?? 'choose a class' }}</div>
                    <div class="ps-muted">{{ count($this->decisions) }} students. Each starts with the rules' recommendation — change anyone, then run.</div>
                    @if ($this->rule)
                        <div class="ps-rule">Rules: {{ $this->rule->summary() }}</div>
                    @endif
                    <div class="ps-recs">
                        @foreach (['promote', 'probation', 'repeat', 'no_results'] as $key)
                            @if ($recommended[$key] ?? 0)
                                <span class="ps-rec ps-rec-{{ $key }}">{{ $recommended[$key] }} {{ strtolower($adviceLabels[$key]) }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="ps-controls">
                    <div>
                        <label class="ps-label">Show</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="show">
                                <option value="all">All students</option>
                                <option value="attention">Needs attention (probation, repeat, no results)</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="ps-label">Promote into</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="targetClassId">
                                <option value="">—</option>
                                @foreach ($this->targetOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="ps-label">Set everyone to</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select x-on:change="$wire.setAll($event.target.value); $event.target.value = ''">
                                <option value="">Choose…</option>
                                @foreach ($actions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>

            <div class="ps-scroll">
                <table class="ps-table">
                    <thead>
                        <tr><th>Student</th><th>Stream</th><th class="ps-c">{{ $this->rule?->basis === 'final_term' ? 'Final term avg.' : 'Year avg.' }}</th><th>Recommendation</th><th>Decision</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($this->reviewRows as $row)
                            @php $s = $row['student']; @endphp
                            <tr wire:key="p-{{ $s->id }}" @class(['ps-flag' => ($this->decisions[$s->id] ?? '') !== 'promote'])>
                                <td><div class="ps-strong">{{ $s->name ?: 'No name' }}</div><div class="ps-muted">{{ $s->admission_no }}</div></td>
                                <td>{{ $s->section?->name ?? '—' }}@if ($row['to_section'] && ($this->decisions[$s->id] ?? '') === 'promote') <span class="ps-muted">→ {{ $row['to_section'] }}</span>@endif</td>
                                <td class="ps-c ps-strong">{{ $row['advice']['average'] ?? '—' }}{{ isset($row['advice']['average']) ? '%' : '' }}</td>
                                <td>
                                    @if ($row['advice'])
                                        <span class="ps-rec ps-rec-{{ $row['advice']['recommendation'] }}">{{ $adviceLabels[$row['advice']['recommendation']] ?? '' }}</span>
                                        <div class="ps-muted ps-reason">{{ $row['advice']['reason'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <select class="ps-select" wire:model.live="decisions.{{ $s->id }}">
                                        @foreach ($actions as $key => $label)
                                            <option value="{{ $key }}">{{ match ($key) {
                                                'promote' => 'Promote to ' . ($this->targetClass?->name ?? '…'),
                                                'probation' => 'Promote to ' . ($this->targetClass?->name ?? '…') . ' on probation',
                                                default => $label,
                                            } }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ps-foot">
                <div class="ps-muted">
                    @foreach ($actions as $key => $label)
                        @if ($counts[$key] ?? 0)<span class="ps-chip">{{ $counts[$key] }} {{ $key === 'promote' ? 'promote' : strtolower(strtok($label, ' (')) }}</span>@endif
                    @endforeach
                </div>
                <div class="ps-controls">
                    <x-filament::button color="gray" wire:click="closeReview">Cancel</x-filament::button>
                    <x-filament::button icon="heroicon-o-arrow-trending-up" wire:click="runPromotion"
                        wire:confirm="Move {{ count($this->decisions) }} students now? You can undo this from the history below.">
                        Run promotion
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── History ── --}}
    @if ($this->history->isNotEmpty())
        <div class="ps-card">
            <div class="ps-head"><div class="ps-title">History</div></div>
            <table class="ps-table">
                <thead><tr><th>When</th><th>Class</th><th>Result</th><th>By</th><th></th></tr></thead>
                <tbody>
                    @foreach ($this->history as $h)
                        <tr @class(['ps-reversed' => $h['reversed']])>
                            <td>{{ $h['when']?->format('j M Y, H:i') }}</td>
                            <td class="ps-strong">{{ $h['class'] }}</td>
                            <td>
                                @foreach ($h['counts'] as $action => $n)
                                    <span class="ps-chip">{{ $n }} {{ ['promote' => 'promoted', 'probation' => 'on probation', 'complete' => 'completed', 'repeat' => 'repeating', 'leave' => 'left'][$action] ?? $action }}</span>
                                @endforeach
                            </td>
                            <td class="ps-muted">{{ $h['by'] }}</td>
                            <td class="ps-r">
                                @if ($h['reversed'])
                                    <span class="ps-muted">Undone</span>
                                @else
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-uturn-left" wire:click="undo('{{ $h['batch'] }}')"
                                        wire:confirm="Put these students back where they were?">Undo</x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <style>
        .ps-intro { font-size: .9rem; color: #16233a; }
        .ps-muted { color: #64748b; font-size: .8rem; }
        .ps-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .ps-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1rem 1.2rem; border-bottom: 1px solid #eef2f7; }
        .ps-title { font-weight: 700; color: #16233a; font-size: 1.02rem; }
        .ps-controls { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .75rem; }
        .ps-label { display: block; font-size: .75rem; font-weight: 600; color: #374151; margin-bottom: .25rem; }
        .ps-scroll { overflow-x: auto; max-height: 60vh; overflow-y: auto; }
        .ps-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
        .ps-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; background: #f8fafc; padding: .6rem .8rem; border-bottom: 1px solid #e4e8f0; position: sticky; top: 0; }
        .ps-table td { padding: .55rem .8rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .ps-c { text-align: center !important; }
        .ps-r { text-align: right !important; }
        .ps-strong { font-weight: 600; color: #16233a; }
        .ps-done { color: #15803d; font-weight: 600; font-size: .82rem; }
        .ps-flag td { background: #fffbeb; }
        .ps-rule { margin-top: .35rem; font-size: .78rem; color: #1e3a5f; background: #eef4fb; display: inline-block; padding: .2rem .55rem; border-radius: 6px; }
        .ps-recs { margin-top: .45rem; display: flex; flex-wrap: wrap; gap: .3rem; }
        .ps-rec { display: inline-block; font-size: .72rem; font-weight: 600; padding: .12rem .5rem; border-radius: 999px; white-space: nowrap; }
        .ps-rec-promote { background: #dcfce7; color: #166534; }
        .ps-rec-probation { background: #fef3c7; color: #92400e; }
        .ps-rec-repeat { background: #fee2e2; color: #991b1b; }
        .ps-rec-no_results { background: #f1f5f9; color: #475569; }
        .ps-reason { margin-top: .15rem; max-width: 22rem; }
        .ps-reversed td { color: #94a3b8; text-decoration: line-through; }
        .ps-select { font-size: .82rem; padding: .35rem .5rem; border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; min-width: 13rem; }
        .ps-chip { display: inline-block; font-size: .75rem; padding: .12rem .5rem; border-radius: 999px; background: #eef4fb; color: #1e3a5f; margin-right: .3rem; }
        .ps-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; padding: .85rem 1.2rem; border-top: 1px solid #eef2f7; background: #fafbfd; }
    </style>
</x-filament-panels::page>
