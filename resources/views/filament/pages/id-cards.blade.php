@php
    $cards = $this->cards;
    $notReady = $this->notReadyCount();
    $explicitSelection = $this->studentId || $this->studentIds !== '';
    $template = $this->cardTemplate;
    $design = $this->design();
    $shared = $this->shared();
    $orientation = $design['orientation'];
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

        <div class="idc-template">
            <span class="idc-swatch" style="background: {{ $design['primary'] }}"></span>
            <span class="idc-swatch" style="background: {{ $design['accent'] }}"></span>
            <span>
                <strong>Template:</strong> {{ \App\Models\IdCardTemplate::ORIENTATIONS[$orientation] }},
                valid {{ $template->validity === 'months' ? 'for '.$template->validity_months.' '.Str::plural('month', $template->validity_months).' from printing' : 'to the end of the academic year' }}
                @unless ($this->canEditTemplate())
                    <span class="idc-muted">· set by the school administrator</span>
                @endunless
            </span>
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

        <div class="idc-layout idc-layout--{{ $orientation }}">
            <div class="idc-fronts">
                @foreach ($cards as $card)
                    <div class="idc-item {{ $card['ready'] ? '' : 'is-incomplete' }}" wire:key="idc-{{ $card['student']->id }}">
                        <div class="idc-scale">@include('id-cards.templates.'.$orientation.'-front')</div>

                        @if (! $card['ready'])
                            <div class="idc-missing-note">
                                Missing: {{ implode(', ', $card['missing']) }}.
                                <a href="{{ \App\Filament\App\Resources\Students\StudentResource::getUrl('edit', ['record' => $card['student']]) }}" target="_blank" class="idc-link">Add it</a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($shared)
                <aside class="idc-back">
                    <div class="idc-back-title">Back of every card</div>
                    <div class="idc-scale">@include('id-cards.templates.'.$orientation.'-back')</div>
                    <p class="idc-muted">The same on every card: school contacts, rules and signature{{ $this->canEditTemplate() ? ' — change the rules under Template.' : '.' }}</p>
                </aside>
            @endif
        </div>
    @endif

    <style>
        @include('id-cards._card-css')

        .idc-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 1.25rem; }
        .idc-filters { flex: 1 1 22rem; max-width: 34rem; }
        .idc-bar { display: flex; gap: .9rem; }
        .idc-bar > div { flex: 1; }
        .idc-field-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .idc-single-note { padding: .75rem 1rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; color: #1e40af; font-size: .85rem; }
        .idc-template { display: flex; align-items: center; gap: .4rem; font-size: .82rem; color: #374151; }
        .idc-swatch { width: 1rem; height: 1rem; border-radius: 4px; border: 1px solid rgba(0, 0, 0, .12); display: inline-block; }
        .idc-muted { color: #64748b; font-size: .78rem; }

        .idc-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 12px; background: #fff; }
        .idc-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: 1rem; }
        .idc-summary { font-size: .9rem; color: #16233a; }
        .idc-warn { color: #b91c1c; }
        .idc-ok { color: #15803d; }
        .idc-actions { display: flex; gap: .5rem; }

        .idc-layout { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 1.25rem; }
        .idc-fronts { flex: 1 1 36rem; display: grid; gap: 1rem; }
        .idc-layout--landscape .idc-fronts { grid-template-columns: repeat(auto-fill, minmax(min(100%, 22rem), 1fr)); }
        .idc-layout--portrait .idc-fronts { grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr)); }
        .idc-item { background: #f8fafc; border: 1px solid #e4e8f0; border-radius: 12px; padding: .9rem; text-align: center; }
        .idc-item.is-incomplete { border-color: #fecaca; background: #fffafa; }
        .idc-scale { zoom: 1.05; display: inline-block; }
        .idc-scale .idc { box-shadow: 0 1px 4px rgba(16, 24, 40, .12); }
        .idc-missing-note { margin-top: .7rem; padding: .45rem .65rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: .78rem; text-align: left; }
        .idc-back { flex: 0 0 auto; position: sticky; top: 5rem; background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: .9rem; text-align: center; max-width: 100%; }
        .idc-back-title { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin-bottom: .6rem; }
        .idc-back .idc-muted { max-width: 20rem; margin: .6rem auto 0; }
        .idc-link { color: #1a5fa8; font-weight: 600; text-decoration: underline; background: none; border: 0; cursor: pointer; padding: 0; font-size: inherit; }
    </style>
</x-filament-panels::page>
