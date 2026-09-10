{{-- ========================================================================== --}}
{{-- STEP INDICATORS                                                           --}}
{{-- ========================================================================== --}}

<div class="flex items-center gap-2 mb-5 text-sm">

    @php
        $steps = [
            'upload' => 'Upload',
            'validating' => 'Validate',
            'preview' => 'Preview',
            'importing' => 'Import',
            'complete' => 'Done',
        ];

        $stepKeys = array_keys($steps);

        $currentIndex = array_search(
            $importStep,
            $stepKeys,
            true
        );

        if ($currentIndex === false) {
            $currentIndex = 0;
        }
    @endphp

    @foreach ($steps as $key => $label)

        @php
            $thisIndex = array_search(
                $key,
                $stepKeys,
                true
            );
        @endphp

        <div class="flex items-center gap-1.5">

            {{-- Current step --}}
            @if ($importStep === $key)

                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold"
                >
                    {{ $loop->iteration }}
                </span>

                <span class="font-semibold text-blue-600 dark:text-blue-400 text-xs">
                    {{ $label }}
                </span>

            {{-- Completed step --}}
            @elseif ($thisIndex < $currentIndex)

                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500 text-white text-xs"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4.5 12.75l6 6 9-13.5"
                        />
                    </svg>
                </span>

                <span class="text-gray-500 dark:text-gray-400 text-xs">
                    {{ $label }}
                </span>

            {{-- Future step --}}
            @else

                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-gray-500 text-xs"
                >
                    {{ $loop->iteration }}
                </span>

                <span class="text-gray-400 dark:text-gray-500 text-xs">
                    {{ $label }}
                </span>

            @endif

            {{-- Connector --}}
            @if (! $loop->last)

                <div class="h-px w-6 bg-gray-300 dark:bg-gray-600"></div>

            @endif

        </div>

    @endforeach

</div>


{{-- ========================================================================== --}}
{{-- STEP 1: UPLOAD                                                            --}}
{{-- ========================================================================== --}}

@if ($importStep === 'upload')

    <div class="text-center mb-5">

        <div
            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/10 mb-3"
        >
            <svg
                class="h-6 w-6 text-blue-600 dark:text-blue-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
                />
            </svg>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            Upload a CSV file with student records.
        </p>

    </div>


    {{-- Upload area --}}
    <div class="mb-5">

        <label
            for="csvFileInput"
            class="
                flex flex-col items-center justify-center
                w-full h-36
                border-2 border-dashed rounded-xl
                cursor-pointer
                transition-colors
                border-gray-300
                hover:border-blue-400
                hover:bg-blue-50/50
                dark:border-gray-600
                dark:hover:border-blue-500
                dark:hover:bg-blue-500/5
            "
        >

            @if ($csvFile)

                {{-- Selected file --}}
                <div class="flex flex-col items-center">

                    <svg
                        class="h-8 w-8 text-green-500 mb-2"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10.125 2.25A9 9 0 0119.5 11.25m-9.375-9v4.5c0 .621.504 1.125 1.125 1.125h4.5"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 15l2.25 2.25L15 12"
                        />
                    </svg>

                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $csvFile->getClientOriginalName() }}
                    </span>

                    <span class="text-xs text-gray-500 mt-0.5">
                        {{ number_format($csvFile->getSize() / 1024, 1) }} KB
                        — Click to change
                    </span>

                </div>

            @else

                {{-- No file selected --}}
                <div class="flex flex-col items-center">

                    <svg
                        class="h-8 w-8 text-gray-400 mb-2"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.338-2.32 3 3 0 013.822 3.652A3.375 3.375 0 0118 19.5H6.75z"
                        />
                    </svg>

                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-semibold text-blue-600 dark:text-blue-400">
                            Click to upload
                        </span>
                        or drag and drop
                    </span>

                    <span class="text-xs text-gray-500 mt-0.5">
                        CSV files only, up to 10 MB
                    </span>

                </div>

            @endif


            {{-- Actual file input --}}
            <input
                id="csvFileInput"
                type="file"
                wire:model="csvFile"
                accept=".csv,.txt"
                class="hidden"
            />

        </label>


        {{-- File validation error --}}
        @error('csvFile')

            <p class="mt-2 text-sm text-red-600 dark:text-red-400">
                {{ $message }}
            </p>

        @enderror

    </div>


    {{-- Validate button --}}
    <div class="text-center mb-5">

<button
    type="button"
    wire:click="uploadAndValidate"
    wire:loading.attr="disabled"
    @disabled(! $csvFile)
    class="
        inline-flex items-center justify-center gap-2
        rounded-lg px-5 py-2.5
        text-sm font-semibold
        bg-blue-600 text-white
        shadow-sm
        hover:bg-blue-500
        transition-colors
        disabled:opacity-50
        disabled:cursor-not-allowed
        dark:bg-blue-500
        dark:hover:bg-blue-400
    "
>
    <span
        wire:loading.remove
        wire:target="uploadAndValidate"
    >
        Validate file
    </span>

    <span
        wire:loading
        wire:target="uploadAndValidate"
        class="flex items-center gap-2"
    >
        <svg
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12 4 12z"
            />
        </svg>

        Validating...
    </span>
</button>

    </div>


    {{-- Column guide --}}
    <div class="border-t border-gray-200 dark:border-white/10 pt-4">

        <p class="text-xs font-semibold text-gray-950 dark:text-white mb-2">
            Required columns
        </p>

        <div class="flex flex-wrap gap-2 mb-3">

            @foreach (['name', 'admission_no', 'class'] as $col)

                <span
                    class="
                        inline-flex items-center gap-1
                        text-xs
                        bg-red-50
                        text-red-700
                        dark:bg-red-500/10
                        dark:text-red-300
                        px-2 py-1
                        rounded-md
                    "
                >

                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>

                    <code>{{ $col }}</code>

                </span>

            @endforeach

        </div>


        <p class="text-xs font-semibold text-gray-950 dark:text-white mb-2">
            Optional columns
        </p>

        <div class="flex flex-wrap gap-1.5">

            @foreach ([
                'section',
                'gender',
                'date_of_birth',
                'admission_date',
                'phone',
                'email',
                'address',
                'medical_notes',
                'guardian_name',
                'guardian_phone',
                'guardian_email',
                'guardian_relationship',
                'status'
            ] as $col)

                <code
                    class="
                        text-xs
                        bg-gray-100
                        dark:bg-gray-800
                        text-gray-600
                        dark:text-gray-400
                        px-1.5 py-0.5
                        rounded
                    "
                >
                    {{ $col }}
                </code>

            @endforeach

        </div>

    </div>

@endif


{{-- ========================================================================== --}}
{{-- STEP 2: VALIDATING                                                        --}}
{{-- ========================================================================== --}}

@if ($importStep === 'validating')

    <div class="text-center py-12">

        <svg
            class="h-10 w-10 animate-spin text-blue-600 mx-auto"
            viewBox="0 0 24 24"
            fill="none"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
            />
        </svg>

        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Reading and validating your CSV file...
        </p>

    </div>

@endif


{{-- ========================================================================== --}}
{{-- STEP 3: PREVIEW                                                          --}}
{{-- ========================================================================== --}}

@if ($importStep === 'preview')

    @php
        $totalRows = (int) ($validationResult['total_rows'] ?? 0);

        $errors = $validationResult['errors'] ?? [];

        $errorRows = array_unique(
            array_filter(
                array_column($errors, 'row')
            )
        );

        $validRows = max(
            0,
            $totalRows - count($errorRows)
        );

        $errorCount = (int) (
            $validationResult['error_count']
            ?? count($errors)
        );

        $duplicateCount = (int) (
            $validationResult['duplicate_count']
            ?? 0
        );

        $preview = $validationResult['preview'] ?? [];
    @endphp


    {{-- Summary cards --}}
    <div class="grid grid-cols-4 gap-3 mb-4">

        {{-- Total --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">

            <div class="text-xl font-bold text-gray-950 dark:text-white">
                {{ number_format($totalRows) }}
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Total
            </div>

        </div>


        {{-- Valid --}}
        <div class="rounded-lg bg-green-50 dark:bg-green-500/10 p-3 text-center">

            <div class="text-xl font-bold text-green-600 dark:text-green-400">
                {{ number_format($validRows) }}
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Valid
            </div>

        </div>


        {{-- Errors --}}
        <div
            class="
                rounded-lg
                {{ $errorCount > 0
                    ? 'bg-red-50 dark:bg-red-500/10'
                    : 'bg-gray-50 dark:bg-gray-800'
                }}
                p-3
                text-center
            "
        >

            <div
                class="
                    text-xl font-bold
                    {{ $errorCount > 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-950 dark:text-white'
                    }}
                "
            >
                {{ number_format($errorCount) }}
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Errors
            </div>

        </div>


        {{-- Duplicates --}}
        <div
            class="
                rounded-lg
                {{ $duplicateCount > 0
                    ? 'bg-amber-50 dark:bg-amber-500/10'
                    : 'bg-gray-50 dark:bg-gray-800'
                }}
                p-3
                text-center
            "
        >

            <div
                class="
                    text-xl font-bold
                    {{ $duplicateCount > 0
                        ? 'text-amber-600 dark:text-amber-400'
                        : 'text-gray-950 dark:text-white'
                    }}
                "
            >
                {{ number_format($duplicateCount) }}
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Duplicates
            </div>

        </div>

    </div>


    {{-- Validation errors --}}
    @if (! empty($errors))

        <div
            class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden mb-4"
            x-data="{ open: false }"
        >

            <button
                type="button"
                @click="open = !open"
                class="
                    w-full
                    flex items-center justify-between
                    px-4 py-2.5
                    text-left
                    hover:bg-gray-50
                    dark:hover:bg-white/5
                    transition-colors
                "
            >

                <div class="flex items-center gap-2">

                    <svg
                        class="h-4 w-4 text-red-500 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                        />
                    </svg>

                    <span class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ count($errors) }} issues found
                    </span>

                </div>

                <svg
                    class="h-4 w-4 text-gray-400 transition-transform"
                    :class="{ 'rotate-180': open }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                    />
                </svg>

            </button>


            <div
                x-show="open"
                x-collapse
                class="border-t border-gray-200 dark:border-white/10"
            >

                <div class="px-4 py-3 max-h-48 overflow-y-auto space-y-1">

                    @foreach ($errors as $error)

                        <div class="flex items-start gap-2 text-xs">

                            @if (($error['row'] ?? 0) > 0)

                                <span
                                    class="
                                        inline-flex items-center
                                        rounded
                                        px-1.5 py-0.5
                                        font-mono
                                        bg-gray-100
                                        text-gray-600
                                        dark:bg-gray-800
                                        dark:text-gray-400
                                        shrink-0
                                    "
                                >
                                    Row {{ $error['row'] }}
                                </span>

                            @endif

                            <span class="text-gray-600 dark:text-gray-400">
                                {{ $error['message'] ?? 'Unknown validation error.' }}
                            </span>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

    @endif


    {{-- Preview table --}}
    @if (! empty($preview))

        <div
            class="
                rounded-lg
                ring-1
                ring-gray-200
                dark:ring-white/10
                overflow-hidden
                mb-4
            "
        >

            <div
                class="
                    px-4 py-2.5
                    border-b
                    border-gray-200
                    dark:border-white/10
                    bg-gray-50
                    dark:bg-white/5
                "
            >

                <span class="text-xs font-semibold text-gray-950 dark:text-white">
                    Preview (first 5 rows)
                </span>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full text-xs">

                    <thead>

                        <tr
                            class="
                                border-b
                                border-gray-200
                                dark:border-white/10
                                bg-gray-50
                                dark:bg-white/5
                            "
                        >

                            @foreach (array_keys($preview[0]) as $header)

                                <th
                                    class="
                                        text-left
                                        py-2 px-3
                                        font-medium
                                        text-gray-500
                                        dark:text-gray-400
                                        whitespace-nowrap
                                    "
                                >
                                    {{ $header }}
                                </th>

                            @endforeach

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">

                        @foreach ($preview as $row)

                            <tr>

                                @foreach ($row as $value)

                                    <td
                                        class="
                                            py-2 px-3
                                            text-gray-700
                                            dark:text-gray-300
                                            whitespace-nowrap
                                        "
                                    >
                                        {{ $value !== null && $value !== '' ? $value : '—' }}
                                    </td>

                                @endforeach

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    @endif


    {{-- Import actions --}}
    <div
        class="
            flex items-center
            justify-between
            flex-wrap
            gap-3
            pt-3
            border-t
            border-gray-200
            dark:border-white/10
        "
    >

        {{-- Background checkbox --}}
        <label
            class="
                flex items-center gap-2
                text-sm
                text-gray-600
                dark:text-gray-400
                cursor-pointer
                select-none
            "
        >

            <input
                type="checkbox"
                wire:model="runInBackground"
                class="
                    rounded
                    border-gray-300
                    text-blue-600
                    shadow-sm
                    focus:ring-blue-500
                    h-4 w-4
                    dark:border-gray-600
                    dark:bg-gray-800
                "
            />

            <span class="text-xs">
                Run in background
            </span>

        </label>


        {{-- Buttons --}}
        <div class="flex items-center gap-2">

            <button
                type="button"
                wire:click="resetImport"
                class="
                    inline-flex items-center justify-center
                    rounded-lg
                    px-3 py-2
                    text-xs font-semibold
                    text-gray-700
                    bg-white
                    ring-1 ring-gray-300
                    shadow-sm
                    hover:bg-gray-50
                    transition-colors
                    dark:text-gray-200
                    dark:bg-gray-800
                    dark:ring-gray-600
                    dark:hover:bg-gray-700
                "
            >
                Cancel
            </button>


            <button
                type="button"
                wire:click="startImport"
                wire:loading.attr="disabled"
                class="
                    inline-flex items-center justify-center gap-1.5
                    rounded-lg
                    px-4 py-2
                    text-xs font-semibold
                    bg-blue-600
                    text-white
                    shadow-sm
                    hover:bg-blue-500
                    transition-colors
                    disabled:opacity-50
                    disabled:cursor-not-allowed
                    dark:bg-blue-500
                    dark:hover:bg-blue-400
                "
            >

                @if (! empty($errors))

                    Import valid records
                    (skip {{ count($errorRows) }})

                @else

                    Import {{ number_format($totalRows) }} students

                @endif

            </button>

        </div>

    </div>


    {{-- Background explanation --}}
    @if ($runInBackground)

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            You can close this and continue working.
            A notification appears when the import finishes.
        </p>

    @endif

@endif


{{-- ========================================================================== --}}
{{-- STEP 4: IMPORTING                                                         --}}
{{-- ========================================================================== --}}

@if ($importStep === 'importing')

    <div wire:poll.2s="refreshProgress">

        {{-- Progress --}}
        <div class="mb-6">

            <div class="flex items-center justify-between mb-2">

                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    Importing students...
                </span>

                <span class="text-sm font-mono font-bold text-blue-600 dark:text-blue-400">
                    {{ $importProgress['progress_percent'] }}%
                </span>

            </div>


            <div
                class="
                    w-full
                    h-2.5
                    bg-gray-200
                    dark:bg-gray-700
                    rounded-full
                    overflow-hidden
                "
            >

                <div
                    class="
                        h-full
                        bg-blue-600
                        rounded-full
                        transition-all
                        duration-700
                        ease-out
                    "
                    style="width: {{ $importProgress['progress_percent'] }}%"
                ></div>

            </div>

        </div>


        {{-- Counters --}}
        <div class="grid grid-cols-4 gap-3 mb-6">

            {{-- Processed --}}
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">

                <div class="text-lg font-bold text-gray-950 dark:text-white">
                    {{ number_format($importProgress['processed_rows']) }}/{{ number_format($importProgress['total_rows']) }}
                </div>

                <div class="text-xs text-gray-500 mt-0.5">
                    Processed
                </div>

            </div>


            {{-- Imported --}}
            <div class="rounded-lg bg-green-50 dark:bg-green-500/10 p-3 text-center">

                <div class="text-lg font-bold text-green-600 dark:text-green-400">
                    {{ number_format($importProgress['successful_rows']) }}
                </div>

                <div class="text-xs text-gray-500 mt-0.5">
                    Imported
                </div>

            </div>


            {{-- Failed --}}
            <div class="rounded-lg bg-red-50 dark:bg-red-500/10 p-3 text-center">

                <div class="text-lg font-bold text-red-600 dark:text-red-400">
                    {{ number_format($importProgress['failed_rows']) }}
                </div>

                <div class="text-xs text-gray-500 mt-0.5">
                    Failed
                </div>

            </div>


            {{-- Remaining --}}
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">

                <div class="text-lg font-bold text-gray-950 dark:text-white">

                    @if ($importProgress['estimated_remaining'] !== null)

                        {{ $this->formatDuration($importProgress['estimated_remaining']) }}

                    @else

                        —

                    @endif

                </div>

                <div class="text-xs text-gray-500 mt-0.5">
                    Remaining
                </div>

            </div>

        </div>


        {{-- Import phases --}}
        <div class="space-y-2.5">

            @php

                $status = $importProgress['status'];

                $phases = [

                    [
                        'label' => 'Reading file',
                        'done' => in_array(
                            $status,
                            ['importing', 'completed'],
                            true
                        ),
                    ],

                    [
                        'label' => 'Validating records',
                        'done' => in_array(
                            $status,
                            ['importing', 'completed'],
                            true
                        ),
                    ],

                    [
                        'label' => 'Importing students',
                        'done' => $status === 'completed',
                        'active' => $status === 'importing',
                    ],

                    [
                        'label' => 'Finalising',
                        'done' => $status === 'completed',
                    ],

                ];

            @endphp


            @foreach ($phases as $phase)

                <div class="flex items-center gap-2.5 text-sm">

                    @if ($phase['done'] ?? false)

                        <svg
                            class="h-5 w-5 text-green-500 shrink-0"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                                clip-rule="evenodd"
                            />
                        </svg>

                        <span class="text-gray-500 dark:text-gray-400">
                            {{ $phase['label'] }}
                        </span>

                    @elseif ($phase['active'] ?? false)

                        <svg
                            class="h-5 w-5 text-blue-600 animate-spin shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />

                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                            />
                        </svg>

                        <span class="font-medium text-gray-950 dark:text-white">
                            {{ $phase['label'] }}...
                        </span>

                    @else

                        <div class="h-5 w-5 flex items-center justify-center shrink-0">

                            <div
                                class="
                                    h-2 w-2
                                    rounded-full
                                    bg-gray-300
                                    dark:bg-gray-600
                                "
                            ></div>

                        </div>

                        <span class="text-gray-400 dark:text-gray-500">
                            {{ $phase['label'] }}
                        </span>

                    @endif

                </div>

            @endforeach

        </div>


        {{-- Activity log --}}
        @if (! empty($importProgress['log']))

            <div
                class="mt-4 pt-4 border-t border-gray-200 dark:border-white/10"
                x-data="{ open: false }"
            >

                <button
                    type="button"
                    @click="open = !open"
                    class="
                        flex items-center gap-1
                        text-xs
                        text-gray-500
                        hover:text-gray-700
                        dark:hover:text-gray-300
                        transition-colors
                    "
                >

                    <svg
                        class="h-3.5 w-3.5 transition-transform"
                        :class="{ 'rotate-90': open }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M8.25 4.5l7.5 7.5-7.5 7.5"
                        />
                    </svg>

                    Activity log

                </button>


                <div
                    x-show="open"
                    x-collapse
                    class="
                        mt-2
                        max-h-32
                        overflow-y-auto
                        font-mono
                        text-xs
                        space-y-0.5
                    "
                >

                    @foreach ($importProgress['log'] as $entry)

                        <div class="text-gray-500 dark:text-gray-400">

                            <span class="text-gray-400 dark:text-gray-500">
                                {{ $entry['time'] ?? '' }}
                            </span>

                            {{ $entry['message'] ?? '' }}

                        </div>

                    @endforeach

                </div>

            </div>

        @endif

    </div>

@endif


{{-- ========================================================================== --}}
{{-- STEP 5: COMPLETE                                                         --}}
{{-- ========================================================================== --}}

@if ($importStep === 'complete')

    <div class="text-center">

        {{-- Successful import --}}
        @if ($importProgress['status'] === 'completed')

            <div
                class="
                    mx-auto
                    flex
                    h-14 w-14
                    items-center
                    justify-center
                    rounded-full
                    bg-green-50
                    dark:bg-green-500/10
                    mb-4
                "
            >

                <svg
                    class="h-8 w-8 text-green-500"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                        clip-rule="evenodd"
                    />
                </svg>

            </div>


            <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">
                Import completed
            </h3>


            {{-- Results --}}
            <div class="grid grid-cols-3 gap-4 mb-6">

                {{-- Imported --}}
                <div>

                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ number_format($importProgress['successful_rows']) }}
                    </div>

                    <div class="text-xs text-gray-500 mt-0.5">
                        Imported
                    </div>

                </div>


                {{-- Failed --}}
                <div>

                    <div
                        class="
                            text-2xl
                            font-bold
                            {{
                                $importProgress['failed_rows'] > 0
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-gray-300 dark:text-gray-600'
                            }}
                        "
                    >
                        {{ number_format($importProgress['failed_rows']) }}
                    </div>

                    <div class="text-xs text-gray-500 mt-0.5">
                        Skipped
                    </div>

                </div>


                {{-- Duration --}}
                <div>

                    <div class="text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $this->formatDuration($importProgress['elapsed_seconds']) }}
                    </div>

                    <div class="text-xs text-gray-500 mt-0.5">
                        Duration
                    </div>

                </div>

            </div>

        @else

            {{-- Failed import --}}
            <div
                class="
                    mx-auto
                    flex
                    h-14 w-14
                    items-center
                    justify-center
                    rounded-full
                    bg-red-50
                    dark:bg-red-500/10
                    mb-4
                "
            >

                <svg
                    class="h-8 w-8 text-red-500"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z"
                        clip-rule="evenodd"
                    />
                </svg>

            </div>


            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                Import failed
            </h3>


            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{
                    $importProgress['errors'][0]['message']
                    ?? 'An unexpected error occurred.'
                }}
            </p>

        @endif


        {{-- Error details --}}
        @if (
            $importProgress['failed_rows'] > 0
            && ! empty($importProgress['errors'])
        )

            <div
                class="
                    rounded-lg
                    ring-1
                    ring-gray-200
                    dark:ring-white/10
                    overflow-hidden
                    mb-4
                    text-left
                "
                x-data="{ open: false }"
            >

                <button
                    type="button"
                    @click="open = !open"
                    class="
                        w-full
                        flex items-center justify-between
                        px-4 py-2.5
                        text-left
                        hover:bg-gray-50
                        dark:hover:bg-white/5
                        transition-colors
                    "
                >

                    <span class="text-xs font-medium text-gray-950 dark:text-white">
                        {{ $importProgress['failed_rows'] }}
                        skipped rows — details
                    </span>

                    <svg
                        class="h-4 w-4 text-gray-400 transition-transform"
                        :class="{ 'rotate-180': open }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                        />
                    </svg>

                </button>


                <div
                    x-show="open"
                    x-collapse
                    class="border-t border-gray-200 dark:border-white/10"
                >

                    <div class="px-4 py-3 max-h-40 overflow-y-auto space-y-1">

                        @foreach ($importProgress['errors'] as $error)

                            <div class="flex items-start gap-2 text-xs">

                                <span
                                    class="
                                        inline-flex items-center
                                        rounded
                                        px-1.5 py-0.5
                                        font-mono
                                        bg-gray-100
                                        text-gray-600
                                        dark:bg-gray-800
                                        dark:text-gray-400
                                        shrink-0
                                    "
                                >
                                    Row {{ $error['row'] ?? '?' }}
                                </span>

                                <span class="text-gray-600 dark:text-gray-400">
                                    {{ $error['message'] ?? 'Unknown error.' }}
                                </span>

                            </div>

                        @endforeach

                    </div>

                </div>

            </div>

        @endif


        {{-- Reset --}}
        <div class="flex items-center justify-center gap-3 mt-4">

            <button
                type="button"
                wire:click="resetImport"
                class="
                    inline-flex
                    items-center
                    justify-center
                    rounded-lg
                    px-4 py-2
                    text-xs
                    font-semibold
                    text-gray-700
                    bg-white
                    ring-1
                    ring-gray-300
                    shadow-sm
                    hover:bg-gray-50
                    transition-colors
                    dark:text-gray-200
                    dark:bg-gray-800
                    dark:ring-gray-600
                    dark:hover:bg-gray-700
                "
            >
                Import another file
            </button>

        </div>

    </div>

@endif