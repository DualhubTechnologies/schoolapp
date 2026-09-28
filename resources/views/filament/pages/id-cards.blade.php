@php
    $cards = $this->cards;
    $notReady = $this->notReadyCount();
    $explicitSelection = $this->studentId || $this->studentIds !== '';
    $template = $this->template;
    $canChoose = $this->canChooseTemplate();
@endphp

<x-filament-panels::page>
    <div class="idc-top">
        <div class="idc-filters">
            @if ($explicitSelection)
                <div class="idc-single-note">
                    {{ $this->studentId ? 'Showing the ID card for one student.' : 'Showing ID cards for the students you selected.' }}
                    <button type="button" wire:click="clearStudent" class="idc-link">Browse by class instead</button>
                </div>
            @else
                <div class="idc-bar">
                    <div>
                        <label class="idc-field-label">Class</label>
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
                        <label class="idc-field-label">Stream</label>
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
        </div>

        <div class="idc-designs">
            <div class="idc-field-label">Card design</div>
            <div class="idc-design-options" role="radiogroup" aria-label="Card design">
                @foreach (\App\Models\School::ID_CARD_TEMPLATES as $key => $label)
                    <button type="button" role="radio" aria-checked="{{ $template === $key ? 'true' : 'false' }}"
                        wire:click="chooseTemplate('{{ $key }}')" @disabled(! $canChoose && $template !== $key)
                        class="idc-design {{ $template === $key ? 'is-active' : '' }}">
                        <span class="idc-design-shape idc-design-shape--{{ $key }}"><span></span></span>
                        <span class="idc-design-text">
                            <strong>{{ Str::before($label, ' (') }}</strong>
                            <small>{{ Str::between($label, '(', ')') }}</small>
                        </span>
                    </button>
                @endforeach
            </div>
            <div class="idc-design-hint">
                {{ $canChoose ? 'Saved for your school — every print and export uses it.' : 'Chosen by the school administrator.' }}
            </div>
        </div>
    </div>

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

        <div class="idc-grid idc-grid--{{ $template }}">
            @foreach ($cards as $card)
                @php($student = $card['student'])
                <div class="idc-item {{ $card['ready'] ? '' : 'is-incomplete' }}" wire:key="idc-{{ $template }}-{{ $student->id }}">
                    <div class="idc-item-cards">
                        <div class="idc-side">
                            <div class="idc-scale">@include('id-cards.templates.'.$template.'-front', ['card' => $card])</div>
                            <span>Front</span>
                        </div>
                        <div class="idc-side">
                            <div class="idc-scale">@include('id-cards.templates.'.$template.'-back', ['card' => $card])</div>
                            <span>Back</span>
                        </div>
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
        @include('id-cards._card-css')

        .idc-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1.25rem; margin-bottom: 1.25rem; }
        .idc-filters { flex: 1 1 22rem; max-width: 34rem; }
        .idc-bar { display: flex; gap: .9rem; }
        .idc-bar > div { flex: 1; }
        .idc-field-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .idc-single-note { padding: .75rem 1rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; color: #1e40af; font-size: .85rem; }

        .idc-designs { flex: 0 1 auto; }
        .idc-design-options { display: flex; gap: .6rem; }
        .idc-design { display: flex; align-items: center; gap: .7rem; padding: .55rem .85rem .55rem .6rem; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; text-align: left; transition: border-color .15s, box-shadow .15s; }
        .idc-design:hover:not(:disabled) { border-color: #94a3b8; }
        .idc-design.is-active { border-color: #13294b; box-shadow: 0 0 0 3px rgba(19, 41, 75, .12); }
        .idc-design:disabled { opacity: .45; cursor: not-allowed; }
        .idc-design-shape { display: block; background: #13294b; border-radius: 3px; position: relative; overflow: hidden; }
        .idc-design-shape span { position: absolute; left: 0; right: 0; background: #fff; }
        .idc-design-shape--classic { width: 38px; height: 24px; }
        .idc-design-shape--classic span { top: 8px; bottom: 4px; border-top: 2px solid #c8a24a; }
        .idc-design-shape--portrait { width: 24px; height: 38px; }
        .idc-design-shape--portrait span { top: 11px; bottom: 4px; border-top: 2px solid #c8a24a; }
        .idc-design-text strong { display: block; font-size: .85rem; color: #16233a; }
        .idc-design-text small { display: block; font-size: .72rem; color: #64748b; }
        .idc-design-hint { margin-top: .35rem; font-size: .72rem; color: #64748b; }

        .idc-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .idc-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: 1rem; }
        .idc-summary { font-size: .9rem; color: #16233a; }
        .idc-warn { color: #b91c1c; }
        .idc-ok { color: #15803d; }
        .idc-actions { display: flex; gap: .5rem; }

        .idc-grid { display: grid; gap: 1rem; }
        .idc-grid--classic { grid-template-columns: repeat(auto-fill, minmax(min(100%, 31rem), 1fr)); }
        .idc-grid--portrait { grid-template-columns: repeat(auto-fill, minmax(min(100%, 25rem), 1fr)); }
        .idc-item { background: #f8fafc; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1rem; }
        .idc-item.is-incomplete { border-color: #fecaca; }
        .idc-item-cards { display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; }
        .idc-side { text-align: center; }
        .idc-side > span { display: block; margin-top: .35rem; font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
        .idc-scale { zoom: .9; }
        .idc-scale .idc { box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
        .idc-grid--portrait .idc-scale { zoom: 1.1; }
        .idc-missing-note { margin-top: .85rem; padding: .5rem .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: .8rem; }
        .idc-link { color: #1a5fa8; font-weight: 600; text-decoration: underline; background: none; border: 0; cursor: pointer; padding: 0; font-size: inherit; }
    </style>
</x-filament-panels::page>
