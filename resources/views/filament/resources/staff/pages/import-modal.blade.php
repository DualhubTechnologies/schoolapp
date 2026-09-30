{{--
    Staff import: upload -> preview -> done. Same look as the student import;
    staff lists are short, so the import runs straight away.
--}}
@php
    $steps = ['upload' => 'Upload', 'preview' => 'Check', 'complete' => 'Done'];
    $stepKeys = array_keys($steps);
    $currentIndex = array_search($importStep, $stepKeys, true) ?: 0;
@endphp

<div class="flex items-center gap-2 mb-5 text-sm">
    @foreach ($steps as $key => $label)
        @php($thisIndex = array_search($key, $stepKeys, true))
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
            Upload a CSV file with your staff. Start from the <strong>CSV template</strong> button: fill it in Excel, then save as CSV.
        </p>
    </div>

    <div class="mb-4">
        <label for="staffCsvInput" class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed rounded-xl cursor-pointer transition-colors border-gray-300 hover:border-blue-400 hover:bg-blue-50/50 dark:border-gray-600 dark:hover:border-blue-500 dark:hover:bg-blue-500/5">
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
            <input id="staffCsvInput" type="file" wire:model="csvFile" accept=".csv,.txt" class="hidden" />
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
            @foreach (\App\Services\StaffCsvImporter::REQUIRED_COLUMNS as $col)
                <span class="inline-flex items-center gap-1 text-xs bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300 px-2 py-1 rounded-md">
                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span><code>{{ $col }}</code>
                </span>
            @endforeach
        </div>

        <p class="text-xs font-semibold text-gray-950 dark:text-white mb-2">Optional columns</p>
        <div class="flex flex-wrap gap-1.5 mb-3">
            @foreach (array_diff(\App\Services\StaffCsvImporter::TEMPLATE_COLUMNS, \App\Services\StaffCsvImporter::REQUIRED_COLUMNS) as $col)
                <code class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded">{{ $col }}</code>
            @endforeach
        </div>

        <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            <li><strong>category</strong>: Teaching or Non-teaching (blank = Teaching). Only teaching staff can be given subjects.</li>
            <li><strong>employment_type</strong>: Permanent, Contract, Part-time or Volunteer. <strong>status</strong>: Active, On leave or Left the school.</li>
            <li><strong>Dates</strong>: DD-MM-YYYY, e.g. 01-02-2024 (DD/MM/YYYY and 1-2-2024 also work). A blank employment date means today.</li>
            <li><strong>basic_salary</strong> in shillings sets the starting salary for payroll. <strong>pays_nssf</strong> / <strong>pays_lst</strong>: Yes or No (blank = Yes).</li>
            <li><strong>Payment</strong>: fill the bank columns, or <em>mobile_money_provider</em> (MTN / Airtel) and <em>mobile_money_number</em>.</li>
            <li>A staff number the school already has is skipped, so a file can safely be uploaded again.</li>
        </ul>
    </div>
@endif

{{-- ── Step 2: preview ── --}}
@if ($importStep === 'preview')
    @php
        $totalRows = (int) ($validationResult['total_rows'] ?? 0);
        $errors = $validationResult['errors'] ?? [];
        $invalidRows = (int) ($validationResult['invalid_rows'] ?? 0);
        $validRows = (int) ($validationResult['valid_rows'] ?? 0);
        $errorCount = (int) ($validationResult['error_count'] ?? count($errors));
        $existingRows = (int) ($validationResult['existing_rows'] ?? 0);
        $existing = $validationResult['existing'] ?? [];
        $preview = $validationResult['preview'] ?? [];
        $unknown = $validationResult['unknown_columns'] ?? [];
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
            <div class="text-xl font-bold text-gray-950 dark:text-white">{{ number_format($totalRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total</div>
        </div>
        <div class="rounded-lg bg-green-50 dark:bg-green-500/10 p-3 text-center">
            <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($validRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">New (will import)</div>
        </div>
        <div class="rounded-lg {{ $existingRows > 0 ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-gray-50 dark:bg-gray-800' }} p-3 text-center">
            <div class="text-xl font-bold {{ $existingRows > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-950 dark:text-white' }}">{{ number_format($existingRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Already registered</div>
        </div>
        <div class="rounded-lg {{ $invalidRows > 0 || $errorCount > 0 ? 'bg-red-50 dark:bg-red-500/10' : 'bg-gray-50 dark:bg-gray-800' }} p-3 text-center">
            <div class="text-xl font-bold {{ $invalidRows > 0 || $errorCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-950 dark:text-white' }}">{{ number_format($invalidRows) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Need fixing</div>
        </div>
    </div>

    @if ($unknown)
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Columns not recognised, ignored: <code>{{ implode(', ', $unknown) }}</code></p>
    @endif

    @foreach ([
        ['items' => $errors, 'total' => $errorCount, 'title' => \Illuminate\Support\Str::plural('problem', $errorCount).' to fix', 'hint' => 'Correct these rows in your file and upload it again. Rows already imported are skipped next time.', 'tone' => 'text-red-500', 'open' => true],
        ['items' => $existing, 'total' => $existingRows, 'title' => 'staff already registered', 'hint' => 'These staff numbers already belong to staff in the school, so the rows are left out. Nothing needs fixing.', 'tone' => 'text-amber-500', 'open' => false],
    ] as $list)
        @continue (empty($list['items']))

        <div class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden mb-4" x-data="{ open: @js($list['open']) }">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2">
                    <svg class="h-4 w-4 {{ $list['tone'] }} shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ number_format($list['total']) }} {{ $list['title'] }}</span>
                </span>
                <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-200 dark:border-white/10">
                <p class="px-4 pt-3 text-xs text-gray-500 dark:text-gray-400">{{ $list['hint'] }}</p>
                <div class="px-4 py-3 max-h-48 overflow-y-auto space-y-1">
                    @foreach ($list['items'] as $error)
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
    @endforeach

    @if (! empty($preview))
        <div class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden mb-4">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                <span class="text-xs font-semibold text-gray-950 dark:text-white">Preview (first 5 rows)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                            @foreach (array_keys($preview[0]) as $header)
                                <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ str_replace('_', ' ', $header) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($preview as $row)
                            <tr>
                                @foreach ($row as $value)
                                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $value !== '' ? $value : '—' }}</td>
                                @endforeach
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
                {{ $validRows === 0 ? 'Nothing new to import' : 'Import '.number_format($validRows).' new '.\Illuminate\Support\Str::plural('staff member', $validRows) }}
            </span>
            <span wire:loading wire:target="startImport">Importing...</span>
        </button>
    </div>
@endif

{{-- ── Step 3: done ── --}}
@if ($importStep === 'complete')
    @php
        $imported = (int) ($importResult['imported'] ?? 0);
        $skipped = (int) ($importResult['skipped'] ?? 0);
        $problems = $importResult['problems'] ?? [];
    @endphp

    <div class="text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-50 dark:bg-green-500/10 mb-4">
            <svg class="h-8 w-8 text-green-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Import completed</h3>

        <div class="grid grid-cols-2 gap-4 mb-4 max-w-xs mx-auto">
            <div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($imported) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Imported</div>
            </div>
            <div>
                <div class="text-2xl font-bold {{ $skipped > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-300 dark:text-gray-600' }}">{{ number_format($skipped) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Skipped</div>
            </div>
        </div>

        @if ($imported > 0)
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Open each staff member to add a photo, allowances and deductions, and to give teachers their subjects.</p>
        @endif

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
