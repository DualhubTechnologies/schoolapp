{{--
    Fees Structure — the sheet a school publishes.

    Styled with the scoped .fs-* rules at the bottom rather than Tailwind
    utilities, so it looks right without rebuilding the theme and prints
    cleanly on A4.
--}}

@php
    $school      = auth()->user()?->school;
    $term        = $this->term;
    $residencies = $this->residencies;
    $sections    = $this->sections;
    $oneOff      = $this->oneOffSections;
    $logo        = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $contacts    = collect([$school?->phone, $school?->email, $school?->website])->filter()->implode('  ·  ');
    $money       = fn ($v) => $v === null ? null : number_format((float) $v, 0);
@endphp

<x-filament-panels::page>

    {{-- ── Toolbar (not printed) ── --}}
    <div class="fs-toolbar fs-no-print">
        <div class="fs-toolbar-field">
            <label for="fs-term" class="fs-label">Term</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="fs-term" wire:model.live="termId">
                    @foreach ($this->termOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <x-filament::button icon="heroicon-o-printer" onclick="window.print()">
            Print
        </x-filament::button>
    </div>

    @if ($residencies->isEmpty())
        <div class="fs-empty fs-empty-warn">
            No residency types set up yet. Add them under <strong>Academics → Residency Types</strong>
            (for example Day and Boarding) — they become the columns on this sheet.
        </div>
    @elseif ($sections->isEmpty() && $oneOff->isEmpty())
        <div class="fs-empty">
            No fees are set up for {{ $term?->label() ?? 'this term' }} yet.
            Add them under <strong>Fees → Fee Setup</strong>.
        </div>
    @else

        <article class="fs-paper">

            {{-- ── Letterhead ── --}}
            <header class="fs-letterhead">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="fs-logo">
                @endif
                <div>
                    <h1 class="fs-school">{{ $school?->name }}</h1>
                    @if ($school?->address)
                        <p class="fs-meta">{{ $school->address }}</p>
                    @endif
                    @if ($contacts)
                        <p class="fs-meta">{{ $contacts }}</p>
                    @endif
                    @if ($school?->motto)
                        <p class="fs-motto">“{{ $school->motto }}”</p>
                    @endif
                </div>
            </header>

            <div class="fs-title">
                <h2>Fees Structure</h2>
                <span class="fs-pill">{{ $term?->label() }}</span>
            </div>

            {{-- ── Termly fees, one table per level ── --}}
            @foreach ($sections as $levelName => $data)
                <section class="fs-block">
                    <h3 class="fs-block-title">{{ $levelName }}</h3>

                    <table class="fs-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                @foreach ($residencies as $residency)
                                    <th class="fs-num">{{ $residency->name }} <span>(UGX)</span></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['rows'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    @foreach ($residencies as $residency)
                                        <td class="fs-num">
                                            {{ $money($row['amounts'][$residency->id] ?? null) ?? '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total per term</td>
                                @foreach ($residencies as $residency)
                                    <td class="fs-num">{{ $money($data['totals'][$residency->id] ?? 0) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </section>
            @endforeach

            {{-- ── One-off fees ── --}}
            @if ($oneOff->isNotEmpty())
                <div class="fs-subtitle">
                    <h2>One-time fees</h2>
                    <p>Paid once, on admission — not part of the termly total.</p>
                </div>

                @foreach ($oneOff as $levelName => $data)
                    <section class="fs-block">
                        <h3 class="fs-block-title">{{ $levelName }}</h3>

                        <table class="fs-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    @foreach ($residencies as $residency)
                                        <th class="fs-num">{{ $residency->name }} <span>(UGX)</span></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        @foreach ($residencies as $residency)
                                            <td class="fs-num">
                                                {{ $money($row['amounts'][$residency->id] ?? null) ?? '—' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total</td>
                                    @foreach ($residencies as $residency)
                                        <td class="fs-num">{{ $money($data['totals'][$residency->id] ?? 0) }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </section>
                @endforeach
            @endif

            <footer class="fs-footer">
                <p>Fees are payable at the beginning of each term. Please quote the student's admission number on every payment.</p>
                <p class="fs-issued">Issued {{ now()->format('j F Y') }}</p>
            </footer>
        </article>
    @endif

    <style>
        .fs-toolbar {
            display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
            max-width: 56rem; width: 100%; margin: 0 auto;
        }
        .fs-toolbar-field { min-width: 16rem; }
        .fs-label { display: block; margin-bottom: .35rem; font-size: .875rem; font-weight: 500; color: #374151; }

        .fs-empty {
            max-width: 56rem; margin: 0 auto; padding: 2.5rem 1.5rem; text-align: center;
            border: 1px dashed #cbd5e1; border-radius: 12px; color: #6b7280; font-size: .9rem;
        }
        .fs-empty-warn { border-color: #fcd34d; background: #fffbeb; color: #92400e; text-align: left; }

        .fs-paper {
            max-width: 56rem; width: 100%; margin: 0 auto; padding: 2.5rem 3rem;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
            box-shadow: 0 1px 3px rgba(13, 31, 56, .06);
            color: #111827;
        }

        .fs-letterhead {
            display: flex; align-items: center; justify-content: center; gap: 1.25rem; text-align: center;
            padding-bottom: 1.25rem; border-bottom: 3px double #1e3a5f;
        }
        .fs-logo { height: 4.5rem; width: 4.5rem; object-fit: contain; }
        .fs-school { font-size: 1.5rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; color: #1e3a5f; }
        .fs-meta { font-size: .85rem; color: #4b5563; margin-top: .15rem; }
        .fs-motto { font-size: .8rem; font-style: italic; color: #6b7280; margin-top: .35rem; }

        .fs-title { display: flex; flex-direction: column; align-items: center; gap: .5rem; margin: 1.75rem 0 1.5rem; }
        .fs-title h2 { font-size: 1.15rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .fs-pill {
            display: inline-block; padding: .2rem .8rem; border-radius: 999px;
            background: #eff6ff; color: #1d4ed8; font-size: .8rem; font-weight: 600;
        }

        .fs-subtitle { margin: 2.25rem 0 1rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; text-align: center; }
        .fs-subtitle h2 { font-size: 1rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .fs-subtitle p { font-size: .8rem; color: #6b7280; margin-top: .25rem; }

        .fs-block { margin-bottom: 1.5rem; break-inside: avoid; }
        .fs-block-title {
            font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: #1e3a5f; margin-bottom: .5rem;
        }

        .fs-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .fs-table th {
            text-align: left; font-size: .72rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
            color: #6b7280; padding: .55rem .75rem; border-bottom: 1.5px solid #1e3a5f;
        }
        .fs-table th span { font-weight: 500; color: #9ca3af; }
        .fs-table td { padding: .6rem .75rem; border-bottom: 1px solid #f1f5f9; }
        .fs-table tbody tr:nth-child(even) td { background: #fafbfc; }
        .fs-table tfoot td {
            font-weight: 700; background: #f1f5fb; border-top: 1.5px solid #1e3a5f; border-bottom: none;
        }
        .fs-table tfoot td.fs-num { font-size: 1rem; color: #1e3a5f; }
        .fs-num { text-align: right !important; font-variant-numeric: tabular-nums; white-space: nowrap; width: 11rem; }

        .fs-footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; text-align: center; font-size: .8rem; color: #4b5563; }
        .fs-issued { margin-top: .35rem; color: #9ca3af; }

        @media (max-width: 640px) {
            .fs-paper { padding: 1.5rem 1rem; }
            .fs-letterhead { flex-direction: column; }
            .fs-num { width: auto; }
        }

        @media print {
            @page { size: A4; margin: 14mm; }

            .fs-no-print,
            .fi-sidebar,
            .fi-topbar,
            .sh-topbar,
            .fi-header,
            .fi-footer { display: none !important; }

            .fi-main, .fi-page, .fi-main-ctn { padding: 0 !important; margin: 0 !important; }
            body, .fi-body { background: #fff !important; }

            .fs-paper { max-width: none; border: none; box-shadow: none; padding: 0; border-radius: 0; }
            .fs-table tbody tr:nth-child(even) td,
            .fs-table tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</x-filament-panels::page>
