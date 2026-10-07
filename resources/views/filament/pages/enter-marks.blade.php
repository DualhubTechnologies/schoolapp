@php
    $assessment = $this->assessment;
    $class = $this->schoolClass;
    $subject = $this->subject;
    $sheet = $this->sheetStudents();
    $students = $sheet['students'];
    $locked = $subject ? $this->isReadOnly() : (bool) $assessment?->isLocked();
    $markSheet = $subject ? $this->markSheet : null;
    $max = (float) ($assessment?->max_score ?? 100);
    $entered = collect($this->scores)->filter(fn ($v) => trim((string) $v) !== '')->count() + collect($this->absent)->filter()->count();
@endphp

<x-filament-panels::page>
    {{-- ── What are we marking? ── --}}
    <div class="em-pickers">
        <div>
            <label class="em-label">Exam</label>
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
            <label class="em-label">Class</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="classId" :disabled="! $assessment">
                    <option value="">Choose…</option>
                    @foreach ($this->classOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div>
            <label class="em-label">Stream</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="sectionId" :disabled="! $class">
                    <option value="">Whole class</option>
                    @foreach ($this->sectionOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div>
            <label class="em-label">Subject</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="subjectId" :disabled="! $class">
                    <option value="">Choose…</option>
                    @foreach ($this->subjectOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    @if (! $assessment)
        <div class="em-empty">No exams yet. Create one under <strong>Exams &amp; Results → Exams</strong>.</div>
    @elseif ($class && $this->subjectOptions()->isEmpty())
        <div class="em-empty">No subjects you can mark in {{ $class->name }}. Subjects and their teachers are set under <strong>Academics → Classes</strong>.</div>
    @elseif (! $subject)
        <div class="em-empty">Choose a class and subject to open the mark sheet.</div>
    @elseif ($students->isEmpty() && ! $subject->pivot->is_compulsory)
        <div class="em-empty">
            <p><strong>{{ $subject->name }}</strong> is an elective, and no learners in {{ $class->name }} have been chosen for it yet.</p>
            <p style="margin: .4rem 0 1rem">Tick the learners who take it, and only they will be on this mark sheet.</p>
            @if ($this->chooseLearnersAction->isVisible()) {{ $this->chooseLearnersAction }} @endif
        </div>
    @elseif ($students->isEmpty())
        <div class="em-empty">No active students in this class{{ $this->sectionId ? ' / stream' : '' }}.</div>
    @else
        <form wire:submit="save"
              class="em-card"
              x-data="{
                  bands: @js($this->bandsForJs()),
                  maths: @js($this->allExams ? $this->gridMaths() : ['exams' => [], 'split' => null]),
                  isAb(v) { return ['ab', 'abs', 'absent'].includes(String(v ?? '').trim().toLowerCase()); },
                  cellBad(v, max) {
                      if (v === null || v === '' || this.isAb(v)) return false;
                      return isNaN(v) || parseFloat(v) < 0 || parseFloat(v) > max;
                  },
                  // The learner's term result, worked out as report cards do.
                  termResult(m) {
                      let weighted = 0, schoolBased = 0, weights = 0, plain = [], averaged = [], averagedWeight = 0;
                      for (const e of this.maths.exams) {
                          const v = m[e.id];
                          if (v === null || v === '' || this.isAb(v) || this.cellBad(v, e.max)) continue;
                          const pct = Math.min(100, parseFloat(v) / e.max * 100);
                          plain.push(pct);
                          if (e.averaged) { averaged.push(pct); averagedWeight = Math.max(averagedWeight, e.weight); continue; }
                          weighted += pct * e.weight;
                          if (e.schoolBased) schoolBased += pct * e.weight;
                          weights += e.weight;
                      }
                      if (averaged.length) {
                          const avg = averaged.reduce((a, b) => a + b, 0) / averaged.length;
                          weighted += avg * averagedWeight; schoolBased += avg * averagedWeight; weights += averagedWeight;
                      }
                      if (!plain.length) return { final: null, formative: null, summative: null };
                      if (weights > 0) return { final: weighted / weights, formative: schoolBased / weights, summative: (weighted - schoolBased) / weights };
                      return { final: plain.reduce((a, b) => a + b, 0) / plain.length, formative: null, summative: null };
                  },
                  fmt(v) { return v === null || v === undefined ? '' : String(Math.round(v * 10) / 10); },
                  gradeFor(pct) {
                      const band = this.bands.find(b => pct >= b.min);
                      return band ? band.grade : '';
                  },
                  max: {{ $max }},
                  dirty: false,
                  timer: null,
                  bad(v) {
                      if (v === null || v === '') return false;
                      return isNaN(v) || parseFloat(v) < 0 || parseFloat(v) > this.max;
                  },
                  changed() {
                      this.dirty = true;
                      clearTimeout(this.timer);
                      this.timer = setTimeout(() => this.autosave(), 3000);
                  },
                  autosave() {
                      if (this.$root.querySelector('.em-score.is-bad')) return;
                      $wire.autosave().then(() => { this.dirty = false; });
                  },
                  init() {
                      window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
                  },
                  grade(v) {
                      if (v === null || v === '' || isNaN(v)) return '';
                      const pct = Math.min(100, (parseFloat(v) / this.max) * 100);
                      const band = this.bands.find(b => pct >= b.min);
                      return band ? band.grade : '';
                  },
                  pct(v) {
                      if (v === null || v === '' || isNaN(v)) return '';
                      return Math.round((parseFloat(v) / this.max) * 1000) / 10 + '%';
                  },
                  next(el) {
                      const inputs = [...el.closest('form').querySelectorAll('.em-score:not([disabled])')];
                      const i = inputs.indexOf(el);
                      if (inputs[i + 1]) { inputs[i + 1].focus(); inputs[i + 1].select(); }
                  },
              }">
            <div class="em-head">
                <div>
                    <div class="em-title">{{ $subject->name }} — {{ $class->name }}{{ $this->sectionId ? ' ' . $this->sectionOptions()[$this->sectionId] : '' }}</div>
                    <div class="em-muted">
                        @if ($this->allExams)
                            All exams of {{ $assessment->term?->label() }}{{ $this->paperCount() > 1 ? ' · Paper '.$this->paper : '' }} · {{ $students->count() }} {{ str('learner')->plural($students->count()) }} · type a mark, or AB for absent
                        @else
                        {{ $assessment->name }}{{ $this->paperCount() > 1 ? ' · Paper '.$this->paper : '' }} · marked out of <strong>{{ $max + 0 }}</strong> · {{ $entered }} of {{ $students->count() }} entered
                        @endif
                        @if ($locked && ! $this->allExams) · <span class="em-locked">{{ $this->readOnlyReason() ?? 'Closed' }} — read only</span> @endif
                    </div>
                </div>
                <div class="em-head-tools">
                    <div class="em-papers" role="group" aria-label="View">
                        <button type="button" wire:click="$set('allExams', false)" @class(['em-paper', 'is-active' => ! $this->allExams])>This exam</button>
                        <button type="button" wire:click="$set('allExams', true)" @class(['em-paper', 'is-active' => $this->allExams])>All exams this term</button>
                    </div>
                    @if ($this->paperCount() > 1)
                        <div class="em-papers" role="group" aria-label="Paper">
                            @foreach (range(1, $this->paperCount()) as $p)
                                <button type="button" wire:click="$set('paper', {{ $p }})" @class(['em-paper', 'is-active' => $this->paper === $p])>Paper {{ $p }}</button>
                            @endforeach
                        </div>
                    @endif
                    @unless ($this->allExams)
                        <label class="em-toggle">
                            <input type="checkbox" wire:model.live="showComments"> Comments
                        </label>
                    @endunless
                </div>
            </div>

            <div class="em-bar">
                <div class="em-bar-status">
                    @if ($this->allExams)
                        <span class="em-muted">To download, print or return one exam's sheet, switch to <strong>This exam</strong>.</span>
                    @else
                    @php $state = $markSheet?->status ?? 'open'; @endphp
                    <span @class(['em-pill', 'is-'.$state, 'is-returned' => $state === 'open' && $markSheet?->returned_note])>
                        {{ $state === 'open' && $markSheet?->returned_note ? 'Returned for correction' : $markSheet?->statusLabel() }}
                    </span>
                    @if ($markSheet?->isSubmitted())
                        <span class="em-muted">by {{ $markSheet->submitter?->name ?? 'a teacher' }}, {{ $markSheet->submitted_at?->format('j M, g:i a') }}</span>
                    @elseif ($markSheet?->isApproved())
                        <span class="em-muted">by {{ $markSheet->approver?->name ?? 'the Director of Studies' }}, {{ $markSheet->approved_at?->format('j M, g:i a') }}</span>
                    @endif
                    @if ($state === 'open' && $markSheet?->returned_note)
                        <span class="em-returned">“{{ $markSheet->returned_note }}”</span>
                    @endif
                    @endif
                </div>
                <div class="em-bar-actions">
                    @if ($this->chooseLearnersAction->isVisible()) {{ $this->chooseLearnersAction }} @endif
                    @if ($this->allExams)
                        @if ($this->submitAllAction->isVisible()) {{ $this->submitAllAction }} @endif
                        @if ($this->approveAllAction->isVisible()) {{ $this->approveAllAction }} @endif
                    @endif
                    @unless ($this->allExams)
                        <x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="downloadSheet">Download sheet</x-filament::button>
                        @if ($this->uploadSheetAction->isVisible()) {{ $this->uploadSheetAction }} @endif
                        <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-o-printer" :href="$this->printUrl()" target="_blank">Print</x-filament::button>
                        <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-o-document" :href="$this->printUrl(blank: true)" target="_blank">Blank sheet</x-filament::button>
                        @if ($this->returnSheetAction->isVisible()) {{ $this->returnSheetAction }} @endif
                        @if ($this->submitSheetAction->isVisible()) {{ $this->submitSheetAction }} @endif
                        @if ($this->approveSheetAction->isVisible()) {{ $this->approveSheetAction }} @endif
                    @endunless
                </div>
            </div>

            @if ($this->allExams)
                @php
                    $exams = $this->termAssessments();
                    $headings = $this->gridHeadings();
                    $editable = $exams->mapWithKeys(fn ($e) => [$e->id => $this->gridEditable($e)]);
                    $maths = $this->gridMaths();
                    $split = $maths['split'];
                    $sheets = $this->gridSheets();
                @endphp
                <div class="em-scroll">
                    <table class="em-table em-grid">
                        <thead>
                            <tr>
                                <th class="em-n">#</th>
                                <th>Student</th>
                                @foreach ($exams as $exam)
                                    <th class="em-c" title="{{ $exam->name }}">
                                        {{ $headings[$exam->id] }}<br><span class="em-out">/{{ $exam->max_score + 0 }}</span>
                                        @php $columnSheet = $sheets[$exam->id] ?? null; @endphp
                                        <br><span @class(['em-pill', 'em-pill-sm', 'is-'.($columnSheet?->status ?? 'open')])>{{ $exam->isLocked() ? 'Locked' : ($columnSheet?->statusLabel() ?? 'Being entered') }}</span>
                                    </th>
                                @endforeach
                                @if ($split)
                                    <th class="em-c em-calc">CA<br><span class="em-out">/{{ $split['formative'] }}</span></th>
                                    <th class="em-c em-calc">Exam<br><span class="em-out">/{{ $split['summative'] }}</span></th>
                                    <th class="em-c em-calc">Total<br><span class="em-out">/100</span></th>
                                @else
                                    <th class="em-c em-calc">Term %</th>
                                @endif
                                <th class="em-c em-calc">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $i => $student)
                                @php $id = $student->id; @endphp
                                <tr wire:key="grid-{{ $id }}"
                                    x-data="{
                                        m: @js(collect($exams)->mapWithKeys(fn ($e) => [$e->id => $this->grid[$e->id][$id] ?? ''])->all()),
                                        get result() { return termResult(this.m); },
                                    }">
                                    <td class="em-n">{{ $i + 1 }}</td>
                                    <td>
                                        <div class="em-name">{{ $student->name ?: 'No name' }}</div>
                                        <div class="em-muted">{{ $student->admission_no }}</div>
                                    </td>
                                    @foreach ($exams as $exam)
                                        <td class="em-c">
                                            <input type="text" autocomplete="off"
                                                   data-exam="{{ $exam->id }}"
                                                   class="em-score em-cell @error('grid.'.$exam->id.'.'.$id) is-error @enderror"
                                                   wire:model="grid.{{ $exam->id }}.{{ $id }}"
                                                   x-model="m[{{ $exam->id }}]"
                                                   :class="{ 'is-bad': cellBad(m[{{ $exam->id }}], {{ (float) $exam->max_score }}) }"
                                                   title="{{ $exam->name }}: 0 to {{ $exam->max_score + 0 }}, or AB"
                                                   @input="changed()"
                                                   @keydown.enter.prevent="next($el)"
                                                   @focus="$el.select()"
                                                   @disabled(! $editable[$exam->id])>
                                        </td>
                                    @endforeach
                                    @if ($split)
                                        <td class="em-c em-calc" x-text="fmt(result.formative)"></td>
                                        <td class="em-c em-calc" x-text="fmt(result.summative)"></td>
                                        <td class="em-c em-calc"><strong x-text="fmt(result.final)"></strong></td>
                                    @else
                                        <td class="em-c em-calc"><strong x-text="result.final === null ? '' : fmt(result.final) + '%'"></strong></td>
                                    @endif
                                    <td class="em-c em-calc"><span class="em-grade" x-text="result.final === null ? '' : gradeFor(result.final)"></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
            <div class="em-scroll">
                <table class="em-table">
                    <thead>
                        <tr>
                            <th class="em-n">#</th>
                            <th>Student</th>
                            <th class="em-c">Score /{{ $max + 0 }}</th>
                            <th class="em-c">%</th>
                            <th class="em-c">Grade</th>
                            <th class="em-c">Absent</th>
                            @if ($this->showComments)<th>Comment</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $i => $student)
                            @php $id = $student->id; @endphp
                            <tr wire:key="row-{{ $id }}" x-data="{ v: @js($this->scores[$id] ?? ''), ab: @js((bool) ($this->absent[$id] ?? false)) }">
                                <td class="em-n">{{ $i + 1 }}</td>
                                <td>
                                    <div class="em-name">{{ $student->name ?: 'No name' }}</div>
                                    <div class="em-muted">{{ $student->admission_no }}</div>
                                </td>
                                <td class="em-c">
                                    <input type="text" inputmode="decimal" autocomplete="off"
                                           class="em-score @error('scores.' . $id) is-error @enderror"
                                           wire:model="scores.{{ $id }}"
                                           x-model="v"
                                           :disabled="ab || {{ $locked ? 'true' : 'false' }}"
                                           :class="{ 'is-bad': !ab && bad(v) }"
                                           :title="!ab && bad(v) ? 'Must be from 0 to {{ $max + 0 }}' : ''"
                                           @input="changed()"
                                           @keydown.enter.prevent="next($el)"
                                           @focus="$el.select()">
                                </td>
                                <td class="em-c em-muted" x-text="ab ? '' : pct(v)"></td>
                                <td class="em-c"><span class="em-grade" x-text="ab ? 'AB' : grade(v)"></span></td>
                                <td class="em-c">
                                    <input type="checkbox" wire:model="absent.{{ $id }}" x-model="ab" @change="changed()" @disabled($locked)>
                                </td>
                                @if ($this->showComments)
                                    <td><input type="text" class="em-comment" wire:model="comments.{{ $id }}" placeholder="Optional" @input="changed()" @disabled($locked)></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @unless ($this->allExams ? ! ($editable ?? collect())->contains(true) : $locked)
                <div class="em-foot">
                    <span class="em-muted">Press <kbd>Enter</kbd> to move to the next {{ $this->allExams ? 'mark' : 'student' }}. Blank = no mark{{ $this->allExams ? ', AB = absent' : '' }}. Marks save by themselves as you type.</span>
                    <span class="em-save">
                        <span class="em-status" x-show="dirty" x-cloak>Unsaved changes…</span>
                        @if ($this->savedAt)
                            <span class="em-status is-saved" x-show="!dirty">✓ Saved {{ $this->savedAt }}</span>
                        @endif
                        <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled" x-on:click="dirty = false">Save marks</x-filament::button>
                    </span>
                </div>
            @endunless
        </form>
    @endif

    <style>
        .em-pickers { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: .9rem; }
        @media (min-width: 768px) { .em-pickers { grid-template-columns: 2fr 1fr 1fr 2fr; } }
        .em-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .em-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .em-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .em-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: 1rem 1.25rem; border-bottom: 1px solid #eef2f7; }
        .em-title { font-weight: 700; color: #16233a; font-size: 1.02rem; }
        .em-muted { color: #64748b; font-size: .8rem; }
        .em-locked { color: #b91c1c; font-weight: 600; }
        .em-note { margin-top: .5rem; font-size: .8rem; color: #92400e; background: #fffbeb; padding: .4rem .6rem; border-radius: 6px; }
        .em-head-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
        .em-papers { display: inline-flex; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
        .em-paper { padding: .3rem .7rem; font-size: .8rem; font-weight: 600; color: #374151; background: #fff; border-right: 1px solid #cbd5e1; }
        .em-paper:last-child { border-right: 0; }
        .em-paper.is-active { background: #1a5fa8; color: #fff; }
        .em-toggle { display: flex; align-items: center; gap: .4rem; font-size: .82rem; color: #374151; white-space: nowrap; }
        .em-scroll { overflow-x: auto; }
        .em-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .em-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: .6rem .75rem; background: #f8fafc; border-bottom: 1px solid #e4e8f0; }
        .em-table td { padding: .45rem .75rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .em-table tbody tr:hover td { background: #f8fbff; }
        .em-n { width: 2.5rem; color: #94a3b8; }
        .em-c { text-align: center !important; }
        .em-name { font-weight: 600; color: #16233a; }
        .em-score { width: 5.5rem; text-align: center; font-weight: 700; font-size: .95rem; padding: .35rem .4rem; border: 1px solid #cbd5e1; border-radius: 7px; }
        .em-score:focus { outline: 2px solid #2472c4; border-color: #2472c4; }
        .em-score:disabled { background: #f1f5f9; color: #94a3b8; }
        .em-score.is-error, .em-score.is-bad { border-color: #dc2626; background: #fef2f2; color: #b91c1c; }
        .em-save { display: flex; align-items: center; gap: .75rem; }
        .em-status { font-size: .8rem; color: #b45309; white-space: nowrap; }
        .em-status.is-saved { color: #15803d; font-weight: 600; }
        .em-grid .em-cell { width: 4.2rem; }
        .em-calc { background: #f8fbff; color: #16233a; }
        .em-table th.em-calc { color: #1a5fa8; }
        .em-out { font-weight: 500; text-transform: none; }
        .em-pill-sm { display: inline-block; margin-top: .25rem; font-size: .58rem; padding: .05rem .4rem; text-transform: none; letter-spacing: 0; white-space: nowrap; }
        .em-grade { display: inline-block; min-width: 2.2rem; font-weight: 700; color: #1a5fa8; }
        .em-comment { width: 100%; min-width: 12rem; padding: .3rem .5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: .82rem; }
        .em-foot { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .85rem 1.25rem; border-top: 1px solid #eef2f7; background: #fafbfd; position: sticky; bottom: 0; }
        .em-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .6rem 1rem; padding: .6rem 1.25rem; border-bottom: 1px solid #eef2f7; background: #fafbfd; }
        .em-bar-status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .em-bar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
        .em-pill { font-size: .72rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: #e0ecfb; color: #1a5fa8; }
        .em-pill.is-submitted { background: #fef3c7; color: #92400e; }
        .em-pill.is-approved { background: #dcfce7; color: #166534; }
        .em-pill.is-returned { background: #fee2e2; color: #991b1b; }
        .em-returned { font-size: .8rem; color: #991b1b; }
        kbd { font-size: .72rem; padding: .05rem .35rem; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; }
    </style>
</x-filament-panels::page>
