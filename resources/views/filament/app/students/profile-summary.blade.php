{{-- The learner at a glance, at the top of their profile (App\Filament\App\Resources\Students\Schemas\StudentForm). --}}
@php
    /** @var \App\Models\Student $student */
    $student = $getRecord();
    $initials = collect(preg_split('/\s+/', trim($student->name)))->filter()->map(fn ($word) => mb_substr($word, 0, 1))->take(2)->implode('');
    $guardian = $student->guardian;
    $guardianDigits = $guardian?->phone ? preg_replace('/\D/', '', $guardian->phone) : null;
    $guardianIntl = $guardianDigits ? (str_starts_with($guardianDigits, '0') ? '256'.substr($guardianDigits, 1) : $guardianDigits) : null;
    // Fee figures and fee pages only for those who handle fees.
    $seesFees = \App\Support\Modules::allows('fees');
    $balance = $student->balance();
    $percent = $student->profilePercent();
    $missing = array_keys(array_filter($student->profileChecklist(), fn (bool $done): bool => ! $done));
    $term = \App\Models\Term::current();
    $statusColor = match ($student->status) {
        'active' => 'success',
        'withdrawn', 'transferred' => 'danger',
        default => 'gray',
    };
    $facts = array_filter([
        'Class' => trim(($student->schoolClass?->name ?? '').($student->section ? ' · '.$student->section->name : '')),
        'Residency' => $student->residencyType?->name,
        'Sex' => \App\Models\Student::GENDERS[$student->gender] ?? null,
        'Age' => $student->age !== null ? $student->age.' years' : null,
        'House' => $student->house?->name,
        'Admitted' => $student->admission_date?->format('j M Y'),
        'LIN' => $student->lin,
    ]);
    $actions = array_filter([
        $seesFees ? ['Receive payment', 'heroicon-o-banknotes', route('filament.app.pages.receive-payment', ['student' => $student->id]), false] : null,
        $seesFees ? ['Fee statement', 'heroicon-o-document-chart-bar', route('filament.app.pages.student-account', ['student' => $student->id]), false] : null,
        $term && $student->school_class_id ? ['Report card', 'heroicon-o-academic-cap', route('filament.app.academics.report-cards', ['term' => $term->id, 'class' => $student->school_class_id, 'student' => $student->id]), true] : null,
        ['Print profile', 'heroicon-o-printer', route('filament.app.students.profile', $student), true],
    ]);
@endphp

<div class="sh-profile-summary overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="grid gap-6 p-5 lg:grid-cols-12 lg:p-6">
        {{-- Who: photo, name, status and key facts. --}}
        <div class="flex min-w-0 gap-4 lg:col-span-7">
            <div class="shrink-0">
                @if ($student->photo)
                    <img src="{{ $student->photoUrl() }}" alt="{{ $student->name }}" class="h-24 w-24 rounded-2xl object-cover ring-1 ring-gray-950/10 sm:h-28 sm:w-28">
                @else
                    <span class="flex h-24 w-24 items-center justify-center rounded-2xl bg-primary-50 text-3xl font-bold text-primary-600 ring-1 ring-primary-600/10 sm:h-28 sm:w-28 dark:bg-primary-500/10 dark:text-primary-400">{{ $initials }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-xl font-bold text-gray-950 dark:text-white">{{ $student->name }}</h2>
                    <x-filament::badge :color="$statusColor">{{ \App\Models\Student::STATUSES[$student->status] ?? ucfirst((string) $student->status) }}</x-filament::badge>
                </div>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Reg. No. <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $student->admission_no }}</span></p>

                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                    @foreach ($facts as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                            <dd class="truncate font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Parent and fees. --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-1 xl:grid-cols-2">
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Parent / guardian</p>
                @if ($guardian)
                    <p class="mt-1 truncate font-semibold text-gray-950 dark:text-white">{{ $guardian->name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ \App\Models\Guardian::RELATIONSHIPS[$guardian->relationship] ?? '' }}{{ $guardian->phone ? ' · '.$guardian->phone : '' }}</p>
                    @if ($guardianIntl)
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-phone" tag="a" :href="'tel:+'.$guardianIntl">Call</x-filament::button>
                            <x-filament::button size="xs" color="success" icon="heroicon-o-chat-bubble-left-right" tag="a" :href="'https://wa.me/'.$guardianIntl" target="_blank">WhatsApp</x-filament::button>
                        </div>
                    @endif
                @else
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">None yet. Add one under Parent and contact below.</p>
                @endif
            </div>

@if ($seesFees)
            <div @class([
                'rounded-xl p-4',
                'bg-danger-50 dark:bg-danger-500/10' => $balance > 0,
                'bg-success-50 dark:bg-success-500/10' => $balance <= 0,
            ])>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $balance > 0 ? 'Fees balance' : ($balance < 0 ? 'In credit' : 'Fees') }}</p>
                <p @class([
                    'mt-1 text-xl font-bold',
                    'text-danger-600 dark:text-danger-400' => $balance > 0,
                    'text-success-600 dark:text-success-400' => $balance <= 0,
                ])>{{ $balance == 0 ? 'Cleared' : 'UGX '.number_format(abs($balance)) }}</p>
                <a href="{{ route('filament.app.pages.student-account', ['student' => $student->id]) }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">View statement</a>
            </div>
            @endif
        </div>
    </div>

    {{-- Profile completeness and quick actions. --}}
    <div class="flex flex-col gap-4 border-t border-gray-100 bg-gray-50/60 px-5 py-4 lg:flex-row lg:items-center lg:justify-between lg:px-6 dark:border-white/10 dark:bg-white/5">
        <div class="min-w-0 lg:max-w-md lg:flex-1">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-200">Profile {{ $percent }}% complete</span>
                @if ($missing === [])
                    <span class="text-success-600 dark:text-success-400">All details added</span>
                @endif
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div @class(['h-full rounded-full', 'bg-success-500' => $percent === 100, 'bg-primary-500' => $percent < 100]) style="width: {{ $percent }}%"></div>
            </div>
            @if ($missing !== [])
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Still to add: {{ implode(', ', $missing) }}.</p>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($actions as [$label, $icon, $url, $newTab])
                <x-filament::button size="sm" color="gray" :icon="$icon" tag="a" :href="$url" :target="$newTab ? '_blank' : null">{{ $label }}</x-filament::button>
            @endforeach
        </div>
    </div>
</div>
