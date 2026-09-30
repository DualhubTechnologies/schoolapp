@php
    $today = $this->today();
    $rows = $this->rows;
    $notTaken = $today->where('taken', 0);
@endphp

<x-filament-panels::page>
    <section class="ar-card">
        <div class="ar-head">
            <div>
                <div class="ar-title">Today, {{ now()->format('l j F') }}</div>
                <div class="ar-muted">
                    {{ $today->sum('present') }} present · {{ $today->sum('absent') }} absent ·
                    registers taken in {{ $today->count() - $notTaken->count() }} of {{ $today->count() }} classes
                </div>
            </div>
            <x-filament::button tag="a" size="sm" icon="heroicon-o-check-circle" :href="\App\Filament\Pages\TakeAttendance::getUrl()">Take a register</x-filament::button>
        </div>
        @if ($today->isEmpty())
            <div class="ar-empty">No classes with learners yet.</div>
        @else
            <div class="ar-today">
                @foreach ($today as $c)
                    <button type="button" wire:click="$set('classId', {{ $c['id'] }})" @class(['ar-chip', 'is-missing' => ! $c['taken']])>
                        <b>{{ $c['class'] }}</b>
                        <span>{{ $c['taken'] ? $c['present'].' / '.$c['learners'].' present' : 'Not taken' }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    <div class="ar-pickers">
        <div>
            <label class="ar-label">Class</label>
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
            <label class="ar-label">Stream</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="sectionId" :disabled="! $this->classId">
                    <option value="">Whole class</option>
                    @foreach ($this->sectionOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div>
            <label class="ar-label">From</label>
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
        </div>
        <div>
            <label class="ar-label">To</label>
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper>
        </div>
    </div>

    @if (! $this->classId)
        <div class="ar-empty">Choose a class to see each learner's attendance over the period.</div>
    @elseif ($rows->isEmpty())
        <div class="ar-empty">No active learners in this class.</div>
    @else
        <section class="ar-card">
            <div class="ar-head">
                <div>
                    <div class="ar-title">{{ $this->classOptions()[$this->classId] }}{{ $this->sectionId ? ' · '.$this->sectionOptions()[$this->sectionId] : '' }}</div>
                    <div class="ar-muted">{{ $this->fromDate()->format('j M Y') }} – {{ $this->toDate()->format('j M Y') }} · lowest attendance first</div>
                </div>
                <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportCsv">Export to Excel</x-filament::button>
            </div>
            <div class="ar-scroll">
                <table class="ar-table">
                    <thead>
                        <tr>
                            <th>Learner</th>
                            <th class="ar-c">Days</th>
                            <th class="ar-c">Present</th>
                            <th class="ar-c">Late</th>
                            <th class="ar-c">Absent</th>
                            <th class="ar-c">Excused</th>
                            <th class="ar-c">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="ar-{{ $row->student->id }}">
                                <td><div class="ar-name">{{ $row->student->name }}</div><div class="ar-muted">{{ $row->student->admission_no }}</div></td>
                                <td class="ar-c">{{ $row->days }}</td>
                                <td class="ar-c">{{ $row->present }}</td>
                                <td class="ar-c">{{ $row->late }}</td>
                                <td class="ar-c">{{ $row->absent }}</td>
                                <td class="ar-c">{{ $row->excused }}</td>
                                <td class="ar-c">
                                    @if ($row->rate === null)
                                        <span class="ar-muted">No register</span>
                                    @else
                                        <span @class(['ar-rate', 'is-low' => $row->rate < 80, 'is-mid' => $row->rate >= 80 && $row->rate < 90])>{{ rtrim(rtrim(number_format($row->rate, 1), '0'), '.') }}%</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <style>
        .ar-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .ar-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem 1rem; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #eef2f7; }
        .ar-title { font-weight: 700; color: #16233a; }
        .ar-muted { color: #64748b; font-size: .8rem; }
        .ar-empty { padding: 2rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .ar-today { display: flex; flex-wrap: wrap; gap: .5rem; padding: 1rem 1.25rem; }
        .ar-chip { display: flex; flex-direction: column; align-items: flex-start; padding: .45rem .75rem; border: 1px solid #bbf7d0; background: #f0fdf4; border-radius: 8px; font-size: .8rem; color: #166534; }
        .ar-chip.is-missing { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
        .ar-pickers { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .9rem; }
        @media (min-width: 768px) { .ar-pickers { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .ar-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .ar-scroll { overflow-x: auto; }
        .ar-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .ar-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: .6rem .75rem; background: #f8fafc; border-bottom: 1px solid #e4e8f0; }
        .ar-table td { padding: .5rem .75rem; border-bottom: 1px solid #f1f5f9; }
        .ar-c { text-align: center !important; }
        .ar-name { font-weight: 600; color: #16233a; }
        .ar-rate { font-weight: 700; color: #166534; }
        .ar-rate.is-mid { color: #92400e; }
        .ar-rate.is-low { color: #b91c1c; }
    </style>
</x-filament-panels::page>
