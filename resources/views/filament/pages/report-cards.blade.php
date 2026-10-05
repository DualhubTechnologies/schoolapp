@php
    $r = $this->results;
    $shown = $this->visibleRows();
    $isHead = \App\Support\AcademicAccess::manages();
    $overall = fn ($row) => match ($r['curriculum'] ?? null) {
        'primary' => $row['division'] ? ($row['aggregate'] !== null ? "Agg. {$row['aggregate']} · {$row['division']}" : 'Incomplete') : '—',
        'a_level' => $row['points'] !== null ? "{$row['points']} points · {$row['result_code']}" : '—',
        'o_level' => $row['uce_label'] ?? '—',
        default => ($row['overall_grade'] ?? null) ? "{$row['overall_grade']} · {$row['overall_descriptor']}" : '—',
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
                <label class="rc-label">Results for</label>
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
                        @if ($isHead)<option value="">Whole class</option>@endif
                        @foreach ($this->sectionOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </div>

    @if ($r && $r['summary']['with_results'])
        <div class="rc-filters">
            <form class="rc-search" wire:submit="applySearch" role="search">
                <label class="rc-label" for="rc-search-input">Search the class</label>
                <div class="rc-search-row">
                    <x-filament::input.wrapper class="rc-search-field" prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input id="rc-search-input" type="search" wire:model="searchInput" placeholder="Name, admission number or LIN" />
                    </x-filament::input.wrapper>
                    <x-filament::button type="submit" icon="heroicon-m-magnifying-glass">Search</x-filament::button>
                    @if ($this->search !== '')
                        <x-filament::button type="button" color="gray" wire:click="clearSearch">Clear</x-filament::button>
                    @endif
                </div>
            </form>
            <div class="rc-filter-grid">
                <div>
                    <label class="rc-label">Sex</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="gender">
                            <option value="">All</option>
                            @foreach (\App\Models\Student::GENDERS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                @if ($this->residencyOptions()->isNotEmpty())
                    <div>
                        <label class="rc-label">Residency</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="residencyId">
                                <option value="">All</option>
                                @foreach ($this->residencyOptions() as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
                <div>
                    <label class="rc-label">Class teacher's comment</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="commentFilter">
                            @foreach (\App\Filament\Pages\ReportCards::COMMENT_FILTERS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
            @if ($this->hasFilters())
                <button type="button" wire:click="clearFilters" class="rc-link rc-clear">Clear search and filters</button>
            @endif
        </div>
    @endif

    <div class="rc-results" wire:loading.class="rc-busy" wire:target="termId, classId, sectionId, applySearch, clearSearch, clearFilters, gender, residencyId, commentFilter">
    <div class="rc-loading" wire:loading.flex wire:target="termId, classId, sectionId, applySearch, clearSearch, clearFilters, gender, residencyId, commentFilter">
        <x-filament::loading-indicator class="rc-spinner" />
        <span>Loading report cards…</span>
    </div>

    @if (! $isHead && ! \App\Support\AcademicAccess::classTeacherStreams())
        <div class="rc-empty">Report cards are written by class teachers. You are not class teacher of any stream — ask the administrator.</div>
    @elseif (! $r)
        <div class="rc-empty">Choose a term and class.</div>
    @elseif (! $r['summary']['with_results'])
        <div class="rc-empty">No marks entered for {{ $r['class']->name }} in {{ $r['term']->label() }} yet.</div>
    @else
        <div class="rc-card">
            <div class="rc-head">
                <div>
                    <strong>{{ $r['class']->name }} — {{ $r['term']->label() }}</strong>
                    <div class="rc-muted">
                        {{ $r['summary']['with_results'] }} students with results. Write or suggest comments, save, then print.
                        @if ($this->hasFilters())
                            <strong>Showing {{ $shown->count() }} of {{ count($r['rows']) }}</strong>{{ $this->search !== '' ? ' matching “'.$this->search.'”' : '' }}.
                        @endif
                    </div>
                </div>
                <div class="rc-actions">
                    <label class="rc-check"><input type="checkbox" wire:model.live="showFees"> Show fees balance</label>
                    <x-filament::button color="gray" icon="heroicon-o-sparkles" wire:click="fillComments">Fill empty comments</x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-check" wire:click="saveComments">Save comments</x-filament::button>
                    <x-filament::button icon="heroicon-o-printer" tag="a" :href="$this->printUrl()" target="_blank" :disabled="$shown->isEmpty()">{{ $this->hasFilters() ? 'Print '.$shown->count().' shown' : 'Print all' }}</x-filament::button>
                </div>
            </div>

            <div class="rc-headnote">
                <label class="rc-label" for="rc-head-comment">Head teacher's comment <span class="rc-muted">— one comment, printed on every report card in {{ $r['class']->name }}{{ $this->sectionId ? ' ' . ($this->sectionOptions()[$this->sectionId] ?? '') : '' }}</span></label>
                @if ($isHead)
                    <textarea id="rc-head-comment" rows="2" class="rc-input" wire:model="headComment" placeholder="e.g. A good term. Let us all work harder next term."></textarea>
                @else
                    <div class="rc-headtext">{{ $this->headComment ?: 'The head teacher has not written it yet.' }}</div>
                @endif
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
                            <th>Conduct</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shown as $row)
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
                        @empty
                            <tr><td colspan="7" class="rc-none">No learner in {{ $r['class']->name }} matches. <button type="button" wire:click="clearFilters" class="rc-link">Clear search and filters</button></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    </div>

    <style>
        .rc-pickers { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .9rem; max-width: 64rem; }
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
        .rc-headnote { padding: .9rem 1.2rem; border-bottom: 1px solid #eef2f7; background: #fafcff; }
        .rc-headnote .rc-input { max-width: 64rem; }
        .rc-headtext { font-size: .85rem; color: #16233a; font-style: italic; }
        .rc-link { color: #1a5fa8; font-weight: 600; font-size: .8rem; }
        .rc-filters { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
        .rc-search { margin-bottom: .8rem; }
        .rc-search-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .rc-search-field { flex: 1 1 16rem; max-width: 32rem; }
        .rc-filter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .75rem .9rem; max-width: 48rem; }
        .rc-clear { display: inline-block; margin-top: .6rem; background: none; border: 0; cursor: pointer; padding: 0; text-decoration: underline; }
        .rc-none { text-align: center; color: #64748b; padding: 1.5rem .6rem !important; }
        .rc-none .rc-link { background: none; border: 0; cursor: pointer; text-decoration: underline; }
        .rc-results { position: relative; min-height: 5rem; }
        .rc-results.rc-busy > :not(.rc-loading) { opacity: .35; pointer-events: none; transition: opacity .15s; }
        .rc-loading { position: absolute; top: 1.25rem; left: 50%; transform: translateX(-50%); z-index: 5; align-items: center; gap: .55rem; padding: .6rem 1rem; background: #fff; border: 1px solid #dbe3ee; border-radius: 999px; box-shadow: 0 4px 14px rgba(16, 24, 40, .12); font-size: .85rem; font-weight: 600; color: #1e3a5f; }
        .rc-spinner { width: 1.1rem; height: 1.1rem; color: #2563eb; }
        @media (max-width: 639px) { .rc-pickers { grid-template-columns: 1fr; } }
    </style>
</x-filament-panels::page>
