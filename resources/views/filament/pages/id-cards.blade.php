@php
    $cards = $this->cards;
    $notReady = $this->notReadyCount();
    $explicitSelection = $this->hasExplicitSelection();
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
                    {{ $this->holderId ? 'Showing one ID card.' : 'Showing ID cards for the people you selected.' }}
                    <button type="button" wire:click="clearSelection" class="idc-link">Show everyone instead</button>
                </div>
            @else
                <form class="idc-search" wire:submit="applySearch" role="search">
                    <label class="idc-field-label" for="idc-search-input">Search</label>
                    <div class="idc-search-row">
                        <x-filament::input.wrapper class="idc-search-field" prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input id="idc-search-input" type="search" wire:model="searchInput" :placeholder="$this->holderType() === 'staff' ? 'Name, staff number, job or phone' : 'Name, admission number, LIN or SchoolPay code'" />
                        </x-filament::input.wrapper>
                        <x-filament::button type="submit" icon="heroicon-m-magnifying-glass">Search</x-filament::button>
                        @if ($this->search !== '')
                            <x-filament::button type="button" color="gray" wire:click="clearSearch">Clear</x-filament::button>
                        @endif
                    </div>
                    @if ($this->search !== '')
                        <div class="idc-muted idc-search-note">Showing matches for “{{ $this->search }}”{{ $this->holderType() === 'students' && ! $this->classId ? ' across the whole school' : '' }}.</div>
                    @endif
                </form>

                @include($this->filtersView())

                @if ($this->hasExtraFilters())
                    <button type="button" wire:click="clearFilters" class="idc-link idc-clear">Clear search and filters</button>
                @endif
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

    <div class="idc-results" wire:loading.class="idc-busy" wire:target="applySearch, clearSearch, clearFilters, clearSelection, classId, sectionId, gender, residencyId, houseId, readiness, category, department">
    <div class="idc-loading" wire:loading.flex wire:target="applySearch, clearSearch, clearFilters, clearSelection, classId, sectionId, gender, residencyId, houseId, readiness, category, department">
        <x-filament::loading-indicator class="idc-spinner" />
        <span>Loading ID cards…</span>
    </div>

    @if ($cards->isEmpty())
        <div class="idc-empty">
            {{ $explicitSelection ? 'No matching records found.' : $this->emptyHint() }}
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
                @if ($shared)
                    <aside class="idc-item idc-back">
                        <div class="idc-back-title">Back of every card</div>
                        <div class="idc-scale">@include('id-cards.templates.'.$orientation.'-back')</div>
                        <p class="idc-muted">The same on every card: school contacts, rules and signature{{ $this->canEditTemplate() ? ' — change the rules under Template.' : '.' }}</p>
                    </aside>
                @endif

                @foreach ($cards as $card)
                    <div class="idc-item {{ $card['ready'] ? '' : 'is-incomplete' }}" wire:key="idc-{{ $card['holder']->id }}">
                        <div class="idc-scale">@include('id-cards.templates.'.$orientation.'-front')</div>

                        @if (! $card['ready'])
                            <div class="idc-missing-note">
                                Missing: {{ implode(', ', $card['missing']) }}.
                                <a href="{{ $this->editUrl($card['holder']) }}" target="_blank" class="idc-link">Add it</a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    </div>

    <style>
        @include('id-cards._card-css')

        .idc-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 1.25rem; }
        .idc-filters { flex: 1 1 40rem; max-width: 64rem; }
        .idc-search { margin-bottom: .9rem; }
        .idc-search-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .idc-search-field { flex: 1 1 16rem; }
        .idc-search-note { margin-top: .35rem; }
        .idc-bar { display: grid; grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr)); gap: .75rem .9rem; }
        .idc-clear { margin-top: .6rem; font-size: .8rem; }
        .idc-results { position: relative; min-height: 6rem; }
        .idc-results.idc-busy > :not(.idc-loading) { opacity: .35; pointer-events: none; transition: opacity .15s; }
        .idc-loading { position: absolute; top: 1.5rem; left: 50%; transform: translateX(-50%); z-index: 5; align-items: center; gap: .55rem; padding: .6rem 1rem; background: #fff; border: 1px solid #dbe3ee; border-radius: 999px; box-shadow: 0 4px 14px rgba(16, 24, 40, .12); font-size: .85rem; font-weight: 600; color: #1e3a5f; }
        .idc-spinner { width: 1.1rem; height: 1.1rem; color: #2563eb; }
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
        .idc-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        @media (max-width: 639px) {
            .idc-top { align-items: stretch; }
            .idc-filters { max-width: none; }
            .idc-fronts { justify-content: center; }
        }

        /*
         * Cards grow with the screen: --idc-zoom scales the real-size (mm)
         * card, and each grid cell is sized to fit one card at that zoom,
         * so the back and the fronts sit side by side with no empty gap.
         */
        .idc-layout { --idc-zoom: 1.05; --idc-card-w: 85.6mm; }
        .idc-layout--portrait { --idc-card-w: 54mm; }
        @media (max-width: 639px) { .idc-layout--landscape { --idc-zoom: .85; } }
        @media (max-width: 400px) { .idc-layout--landscape { --idc-zoom: .75; } .idc-item { padding: .7rem; } }
        @media (min-width: 1280px) { .idc-layout { --idc-zoom: 1.3; } }
        @media (min-width: 1600px) { .idc-layout { --idc-zoom: 1.5; } }
        @media (min-width: 2000px) { .idc-layout { --idc-zoom: 1.75; } }
        .idc-fronts { display: grid; gap: 1.25rem; align-items: start; justify-content: start; grid-template-columns: repeat(auto-fill, minmax(min(100%, calc(var(--idc-card-w) * var(--idc-zoom) + 2rem)), max-content)); }
        .idc-item { background: #f8fafc; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1rem; text-align: center; }
        .idc-item.is-incomplete { border-color: #fecaca; background: #fffafa; }
        .idc-scale { zoom: var(--idc-zoom); display: inline-block; max-width: 100%; }
        .idc-scale .idc { box-shadow: 0 1px 4px rgba(16, 24, 40, .12); }
        .idc-missing-note { margin-top: .7rem; padding: .45rem .65rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: .78rem; text-align: left; }
        .idc-back { background: #fff; }
        .idc-back-title { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin-bottom: .6rem; }
        .idc-back .idc-muted { max-width: 22rem; margin: .7rem auto 0; }
        .idc-link { color: #1a5fa8; font-weight: 600; text-decoration: underline; background: none; border: 0; cursor: pointer; padding: 0; font-size: inherit; }
    </style>
</x-filament-panels::page>
