{{--
    Fees Structure — the sheet a school publishes.
    Save as: resources/views/filament/pages/fee-structure-sheet.blade.php
--}}

@php
    $school      = auth()->user()?->school;
    $term        = $this->term;
    $residencies = $this->residencies;
    $sections    = $this->sections;
    $oneOff      = $this->oneOffSections;
@endphp

<x-filament-panels::page>

    {{-- Controls, hidden when printing --}}
    <div class="sh-no-print mb-6 flex flex-wrap items-end gap-4">
        <div class="min-w-64">
            <label class="mb-1 block text-sm font-medium">Term</label>
            <select wire:model.live="termId"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                @foreach ($this->termOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="button"
                onclick="window.print()"
                class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">
            Print
        </button>
    </div>

    @if ($residencies->isEmpty())
        <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 p-6 text-sm text-amber-800">
            No residency types set up yet. Add them under Admin Settings → Residency Types
            (for example Day and Boarding) — they become the columns on this sheet.
        </div>
    @elseif ($sections->isEmpty() && $oneOff->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500 dark:border-white/10">
            No fees set up for this term yet.
        </div>
    @else

        {{-- Letterhead --}}
        <div class="mb-6 text-center">
            <h1 class="text-xl font-bold">{{ $school?->name }}</h1>
            @if ($school?->address)
                <p class="text-sm text-gray-500">{{ $school->address }}</p>
            @endif
            @if ($school?->motto)
                <p class="text-xs italic text-gray-400">{{ $school->motto }}</p>
            @endif
            <p class="mt-3 text-base font-semibold uppercase tracking-wide">Fees Structure</p>
            <p class="text-sm text-gray-500">{{ $term?->label() }}</p>
        </div>

        {{-- ── Termly fees ── --}}
        @foreach ($sections as $sectionName => $levels)
            @if ($sectionName)
                <h2 class="mb-3 mt-8 text-base font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">
                    {{ $sectionName }}
                </h2>
            @endif

            @foreach ($levels as $levelName => $data)
                <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/5">
                        <h3 class="text-sm font-semibold">{{ $levelName }}</h3>
                    </div>

                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500">
                            <tr class="border-b border-gray-100 dark:border-white/10">
                                <th class="px-4 py-2.5">Category</th>
                                @foreach ($residencies as $residency)
                                    <th class="px-4 py-2.5 text-right">{{ $residency->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($data['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-2.5">{{ $row['name'] }}</td>
                                    @foreach ($residencies as $residency)
                                        <td class="px-4 py-2.5 text-right">
                                            @if (($row['amounts'][$residency->id] ?? null) !== null)
                                                {{ number_format($row['amounts'][$residency->id], 0) }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <td class="px-4 py-3 font-semibold">Total per term</td>
                                @foreach ($residencies as $residency)
                                    <td class="px-4 py-3 text-right text-base font-bold">
                                        {{ number_format($data['totals'][$residency->id] ?? 0, 0) }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach
        @endforeach

        {{-- ── One-off fees ── --}}
        @if ($oneOff->isNotEmpty())
            <h2 class="mb-3 mt-8 text-base font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">
                One-time fees (paid on admission)
            </h2>

            @foreach ($oneOff as $sectionName => $levels)
                @foreach ($levels as $levelName => $data)
                    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                        <div class="border-b border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/5">
                            <h3 class="text-sm font-semibold">
                                {{ $sectionName ? "{$sectionName} — {$levelName}" : $levelName }}
                            </h3>
                        </div>

                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase text-gray-500">
                                <tr class="border-b border-gray-100 dark:border-white/10">
                                    <th class="px-4 py-2.5">Item</th>
                                    @foreach ($residencies as $residency)
                                        <th class="px-4 py-2.5 text-right">{{ $residency->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($data['rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-2.5">{{ $row['name'] }}</td>
                                        @foreach ($residencies as $residency)
                                            <td class="px-4 py-2.5 text-right">
                                                @if (($row['amounts'][$residency->id] ?? null) !== null)
                                                    {{ number_format($row['amounts'][$residency->id], 0) }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                                <tr>
                                    <td class="px-4 py-3 font-semibold">Total</td>
                                    @foreach ($residencies as $residency)
                                        <td class="px-4 py-3 text-right font-bold">
                                            {{ number_format($data['totals'][$residency->id] ?? 0, 0) }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endforeach
            @endforeach
        @endif

        <p class="mt-8 text-center text-xs text-gray-500">
            Fees are payable at the beginning of each term.
        </p>
    @endif

    @push('styles')
        <style>
            @media print {
                .sh-no-print,
                .fi-sidebar,
                .fi-topbar,
                .sh-topbar,
                .fi-header,
                .fi-footer { display: none !important; }

                .fi-main { padding: 0 !important; }

                /* Keep a level's table from splitting across pages. */
                table { break-inside: avoid; }
            }
        </style>
    @endpush
</x-filament-panels::page>
