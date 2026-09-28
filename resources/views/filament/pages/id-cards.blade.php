@php
    $cards = $this->cards;
    $notReady = $this->notReadyCount();
    $explicitSelection = $this->studentId || $this->studentIds !== '';
@endphp

<x-filament-panels::page>
    @if ($explicitSelection)
        <div class="idc-single-note">
            {{ $this->studentId ? 'Printing an ID card for one student.' : 'Printing ID cards for the students you selected.' }}
            <button type="button" wire:click="clearStudent" class="idc-link">Browse by class instead</button>
        </div>
    @else
        <div class="idc-bar">
            <div>
                <label class="idc-label">Class</label>
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
                <label class="idc-label">Stream</label>
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
    @endif

    @if ($cards->isEmpty())
        <div class="idc-empty">
            {{ $explicitSelection ? 'No matching students found.' : 'Choose a class to preview its ID cards.' }}
        </div>
    @else
        <div class="idc-head">
            <div class="idc-summary">
                <strong>{{ $cards->count() }}</strong> {{ Str::plural('card', $cards->count()) }}
                @if ($notReady > 0)
                    <span class="idc-warn">— {{ $notReady }} {{ $notReady === 1 ? 'is' : 'are' }} missing details and cannot be printed yet</span>
                @else
                    <span class="idc-ok">— all ready to print</span>
                @endif
            </div>
            <div class="idc-actions">
                @if ($this->canPrint())
                    <x-filament::button icon="heroicon-o-printer" tag="a" :href="$this->printUrl()" target="_blank">
                        Print
                    </x-filament::button>
                    <x-filament::button icon="heroicon-o-arrow-down-tray" color="gray" tag="a" :href="$this->exportUrl()" target="_blank">
                        Export PDF
                    </x-filament::button>
                @else
                    <x-filament::button icon="heroicon-o-printer" disabled>Print</x-filament::button>
                    <x-filament::button icon="heroicon-o-arrow-down-tray" color="gray" disabled>Export PDF</x-filament::button>
                @endif
            </div>
        </div>

        <div class="idc-grid">
            @foreach ($cards as $card)
                @php($student = $card['student'])
                <div class="idc-item" wire:key="idc-{{ $student->id }}">
                    <div class="idc-pair">
                        @include('id-cards._front', ['card' => $card])
                        @include('id-cards._back', ['card' => $card])
                    </div>

                    @if (! $card['ready'])
                        <div class="idc-missing-note">
                            <strong>{{ $student->name }}</strong> is missing: {{ implode(', ', $card['missing']) }}.
                            <a href="{{ \App\Filament\App\Resources\Students\StudentResource::getUrl('edit', ['record' => $student]) }}" target="_blank" class="idc-link">Add it</a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <style>
        @include('id-cards._styles')

        .idc-bar { display: flex; gap: .9rem; margin-bottom: 1.25rem; max-width: 32rem; }
        .idc-bar > div { flex: 1; }
        .idc-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .idc-single-note { padding: .75rem 1rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; color: #1e40af; font-size: .85rem; margin-bottom: 1.25rem; }
        .idc-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .idc-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: 1rem; }
        .idc-summary { font-size: .9rem; color: #16233a; }
        .idc-warn { color: #b91c1c; }
        .idc-ok { color: #15803d; }
        .idc-actions { display: flex; gap: .5rem; }
        .idc-grid { display: flex; flex-direction: column; gap: 1.25rem; }
        .idc-item { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1rem; }
        .idc-missing-note { margin-top: .75rem; padding: .5rem .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: .8rem; }
        .idc-link { color: #1a5fa8; font-weight: 600; text-decoration: underline; background: none; border: 0; cursor: pointer; padding: 0; font-size: inherit; }
    </style>
</x-filament-panels::page>
