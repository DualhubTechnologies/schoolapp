{{--
    Opening balances: upload -> check -> done. Same look as the staff and
    student imports; the file is checked and saved straight away.
--}}
@php
    $steps = ['upload' => 'Upload', 'preview' => 'Check', 'complete' => 'Done'];
    $stepKeys = array_keys($steps);
    $currentIndex = array_search($importStep, $stepKeys, true) ?: 0;
@endphp

<div class="flex items-center gap-2 mb-5 text-sm">
    @foreach ($steps as $key => $label)
        {{-- Block form on purpose: the one-line form breaks templates that also use blocks. --}}
        @php
            $thisIndex = array_search($key, $stepKeys, true);
        @endphp
        <div class="flex items-center gap-1.5">
            @if ($importStep === $key)
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold">{{ $loop->iteration }}</span>
                <span class="font-semibold text-blue-600 dark:text-blue-400 text-xs">{{ $label }}</span>
            @elseif ($thisIndex < $currentIndex)
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500 text-white text-xs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </span>
                <span class="text-gray-500 dark:text-gray-400 text-xs">{{ $label }}</span>
            @else
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-gray-500 text-xs">{{ $loop->iteration }}</span>
                <span class="text-gray-400 dark:text-gray-500 text-xs">{{ $label }}</span>
            @endif

            @if (! $loop->last)
                <div class="h-px w-6 bg-gray-300 dark:bg-gray-600"></div>
            @endif
        </div>
    @endforeach
</div>

{{-- ── Step 1: upload ── --}}
@if ($importStep === 'upload')
    <div class="text-center mb-5">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/10 mb-3">
            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Bring each learner's balance over from your old system. Download the <strong>Opening balances sheet</strong>: it lists your learners.
            Fill in the <code>balance</code> column in Excel, save as CSV and upload it here.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 mb-4">
        <div>
            <label for="openingTermInput" class="block text-sm font-medium text-gray-950 dark:text-white mb-1">Carry the balances into</label>
            <x-filament::input.wrapper :valid="! $errors->has('openingTermId')">
                <x-filament::input.select id="openingTermInput" wire:model="openingTermId">
                    <option value="">Choose a term</option>
                    @foreach ($terms as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            @error('openingTermId')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-start gap-3 rounded-lg ring-1 ring-gray-200 dark:ring-white/10 p-3 cursor-pointer">
            <x-filament::input.checkbox wire:model="includesTermFees" class="mt-0.5" />
            <span class="text-sm">
                <span class="block font-medium text-gray-950 dark:text-white">My old system already charged this term's fees</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tick only when switching in the middle of a term. SchoolHub then won't bill these learners for the term again. Put 0 for learners who owe nothing.</span>
            </span>
        </label>
    </div>

    <div class="mb-4">
        <label for="openingBalancesCsvInput" class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed rounded-xl cursor-pointer transition-colors border-gray-300 hover:border-blue-400 hover:bg-blue-50/50 dark:border-gray-600 dark:hover:border-blue-500 dark:hover:bg-blue-500/5">
            @if ($csvFile)
                <div class="flex flex-col items-center">
                    <svg class="h-8 w-8 text-green-500 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15l2.25 2.25L15 12M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $csvFile->getClientOriginalName() }}</span>
                    <span class="text-xs text-gray-500 mt-0.5">{{ number_format($csvFile->getSize() / 1024, 1) }} KB — Click to change</span>
                </div>
            @else
                <div class="flex flex-col items-center">
                    <svg class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.338-2.32 3 3 0 013.822 3.652A3.375 3.375 0 0118 19.5H6.75z" />
                    </svg>
                    <span class="text-sm text-gray-600 dark:text-gray-400"><span class="font-semibold text-blue-600 dark:text-blue-400">Click to upload</span> or drag and drop</span>
                    <span class="text-xs text-gray-500 mt-0.5">CSV files only, up to 5 MB</span>
                </div>
            @endif
            <input id="openingBalancesCsvInput" type="file" wire:model="csvFile" accept=".csv,.txt" class="hidden" />
        </label>

        @error('csvFile')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="text-center mb-5">
        <button type="button" wire:click="uploadAndValidate" wire:loading.attr="disabled" @disabled(! $csvFile)
            class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold bg-blue-600 text-white shadow-sm hover:bg-blue-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-500 dark:hover:bg-blue-400">
            <span wire:loading.remove wire:target="uploadAndValidate,csvFile">Check file</span>
            <span wire:loading wire:target="uploadAndValidate,csvFile" class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                Checking...
            </span>
        </button>
    </div>

    <div class="border-t border-gray-200 dark:border-white/10 pt-4">
        <p class="text-xs font-semibold text-gray-950 dark:text-white mb-2">Required columns</p>
        <div class="flex flex-wrap gap-2 mb-3">
            @foreach (\App\Services\OpeningBalanceCsvImporter::REQUIRED_COLUMNS as $col)
                <span class="inline-flex items-center gap-1 text-xs bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300 px-2 py-1 rounded-md">
                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span><code>{{ $col }}</code>
                </span>
            @endforeach
        </div>

        <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            <li><strong>balance</strong>: what the learner owes in shillings, e.g. 450000 or 450,000/=. A learner in credit: -50000. Blank: nothing to bring over.</li>
            <li><strong>name</strong> and <strong>class</strong> on the sheet are there to help you; they are not imported.</li>
            <li>Each learner has one opening balance. Uploading a file again replaces it, so a corrected file never adds a second one; <strong>0</strong> removes it.</li>
            <li>Learners must be in SchoolHub first: import or admit them. Learners who have left can have a balance too.</li>
            <li>Balances from before this term? Leave the box unticked: SchoolHub adds this term's fees when you bill the term.</li>
        </ul>
    </div>
@endif

{{-- ── Step 2: check ── --}}
@if ($importStep === 'preview')
    @php
        $totalRows = (int) ($validationResult['total_rows'] ?? 0);
        $errors = $validationResult['errors'] ?? [];
        $invalidRows = (int) ($validationResult['invalid_rows'] ?? 0);
        $validRows = (int) ($validationResult['valid_rows'] ?? 0);
        $replacingRows = (int) ($validationResult['replacing_rows'] ?? 0);
        $blankRows = (int) ($validationResult['blank_rows'] ?? 0);
        $errorCount = (int) ($validationResult['error_count'] ?? count($errors));
        $owed = (float) ($validationResult['total_owed'] ?? 0);
        $credit = (float) ($validationResult['total_credit'] ?? 0);
        $unbilled = (int) ($validationResult['unbilled_learners'] ?? 0);
        $preview = $validationResult['preview'] ?? [];
        $unknown = $validationResult['unknown_columns'] ?? [];
    @endphp

    @if ($termLabel)
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Into <strong class="text-gray-950 dark:text-white">{{ $termLabel }}</strong>.
            {{ $includesTermFees ? "The balances include this term's fees, so these learners won't be billed for it again." : "SchoolHub adds this term's fees on top when you bill the term." }}
        </p>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
            <div class="text-xl font-bold text-gray-950 dark:text-white">{{ number_format($totalRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Rows</div>
        </div>
        <div class="rounded-lg bg-green-50 dark:bg-green-500/10 p-3 text-center">
            <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($validRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Balances to save</div>
        </div>
        <div class="rounded-lg {{ $replacingRows > 0 ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-gray-50 dark:bg-gray-800' }} p-3 text-center">
            <div class="text-xl font-bold {{ $replacingRows > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-950 dark:text-white' }}">{{ number_format($replacingRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Replace an earlier figure</div>
        </div>
        <div class="rounded-lg {{ $invalidRows > 0 || $errorCount > 0 ? 'bg-red-50 dark:bg-red-500/10' : 'bg-gray-50 dark:bg-gray-800' }} p-3 text-center">
            <div class="text-xl font-bold {{ $invalidRows > 0 || $errorCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-950 dark:text-white' }}">{{ number_format($invalidRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Need fixing</div>
        </div>
    </div>

    @if ($validRows > 0)
        <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
            Check the totals against your old system: <strong>UGX {{ number_format($owed) }}</strong> owed and <strong>UGX {{ number_format($credit) }}</strong> in credit.
        </p>
    @endif

    @if ($blankRows > 0)
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ number_format($blankRows) }} {{ \Illuminate\Support\Str::plural('row', $blankRows) }} with no balance to bring over {{ $blankRows === 1 ? 'is' : 'are' }} left out.</p>
    @endif

    @if ($unbilled > 0)
        <div class="rounded-lg bg-amber-50 dark:bg-amber-500/10 px-4 py-3 mb-4 text-xs text-amber-800 dark:text-amber-300">
            <strong>{{ number_format($unbilled) }} active {{ \Illuminate\Support\Str::plural('learner', $unbilled) }}</strong> {{ $unbilled === 1 ? 'has' : 'have' }} no figure in this file, so billing {{ $termLabel ?? 'the term' }} would still charge them its fees.
            Put 0 for learners who owe nothing.
        </div>
    @endif

    @if ($unknown)
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Columns not recognised, ignored: <code>{{ implode(', ', $unknown) }}</code></p>
    @endif

    @if (! empty($errors))
        <div class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden mb-4" x-data="{ open: true }">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ number_format($errorCount) }} {{ \Illuminate\Support\Str::plural('problem', $errorCount) }} to fix</span>
                </span>
                <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-200 dark:border-white/10">
                <p class="px-4 pt-3 text-xs text-gray-500 dark:text-gray-400">Correct these rows in your file and upload it again. Rows already saved are replaced, never added twice.</p>
                <div class="px-4 py-3 max-h-48 overflow-y-auto space-y-1">
                    @foreach ($errors as $error)
                        <div class="flex items-start gap-2 text-xs">
                            @if (($error['row'] ?? 0) > 0)
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 font-mono bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 shrink-0">Row {{ $error['row'] }}</span>
                            @endif
                            <span class="text-gray-600 dark:text-gray-400">{{ $error['message'] ?? 'Unknown problem.' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if (! empty($preview))
        <div class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden mb-4">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                <span class="text-xs font-semibold text-gray-950 dark:text-white">Preview (first 5 balances)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                            <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Admission no.</th>
                            <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Learner in SchoolHub</th>
                            <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Class</th>
                            <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($preview as $row)
                            <tr>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['admission_no'] }}</td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['learner'] }}</td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['class'] !== '' ? $row['class'] : '—' }}</td>
                                <td class="py-2 px-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['balance'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="flex items-center justify-end flex-wrap gap-2 pt-3 border-t border-gray-200 dark:border-white/10">
        <button type="button" wire:click="resetImport" class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold text-gray-700 bg-white ring-1 ring-gray-300 shadow-sm hover:bg-gray-50 transition-colors dark:text-gray-200 dark:bg-gray-800 dark:ring-gray-600 dark:hover:bg-gray-700">
            Upload a different file
        </button>
        <button type="button" wire:click="startImport" wire:loading.attr="disabled" @disabled($validRows === 0)
            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold bg-blue-600 text-white shadow-sm hover:bg-blue-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-500 dark:hover:bg-blue-400">
            <span wire:loading.remove wire:target="startImport">
                {{ $validRows === 0 ? 'Nothing to save' : 'Save '.number_format($validRows).' opening '.\Illuminate\Support\Str::plural('balance', $validRows) }}
            </span>
            <span wire:loading wire:target="startImport">Saving...</span>
        </button>
    </div>
@endif

{{-- ── Step 3: done ── --}}
@if ($importStep === 'complete')
    @php
        $added = (int) ($importResult['added'] ?? 0);
        $replaced = (int) ($importResult['replaced'] ?? 0);
        $removed = (int) ($importResult['removed'] ?? 0);
        $skipped = (int) ($importResult['skipped'] ?? 0);
        $problems = $importResult['problems'] ?? [];
    @endphp

    <div class="text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-50 dark:bg-green-500/10 mb-4">
            <svg class="h-8 w-8 text-green-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Opening balances saved</h3>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4 max-w-md mx-auto">
            <div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($added) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Added</div>
            </div>
            <div>
                <div class="text-2xl font-bold {{ $replaced > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-300 dark:text-gray-600' }}">{{ number_format($replaced) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Replaced</div>
            </div>
            <div>
                <div class="text-2xl font-bold {{ $removed > 0 ? 'text-gray-950 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ number_format($removed) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Removed</div>
            </div>
            <div>
                <div class="text-2xl font-bold {{ $skipped > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-300 dark:text-gray-600' }}">{{ number_format($skipped) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Skipped</div>
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Each learner's statement now starts with the balance from your old system.</p>

        @if ($problems)
            <div class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden mb-4 text-left">
                <div class="px-4 py-2.5 text-xs font-medium text-gray-950 dark:text-white border-b border-gray-200 dark:border-white/10">Skipped rows</div>
                <div class="px-4 py-3 max-h-40 overflow-y-auto space-y-1">
                    @foreach ($problems as $error)
                        <div class="flex items-start gap-2 text-xs">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 font-mono bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 shrink-0">Row {{ $error['row'] ?? '?' }}</span>
                            <span class="text-gray-600 dark:text-gray-400">{{ $error['message'] ?? 'Unknown problem.' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <button type="button" wire:click="resetImport" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-xs font-semibold text-gray-700 bg-white ring-1 ring-gray-300 shadow-sm hover:bg-gray-50 transition-colors dark:text-gray-200 dark:bg-gray-800 dark:ring-gray-600 dark:hover:bg-gray-700">
            Import another file
        </button>
    </div>
@endif
