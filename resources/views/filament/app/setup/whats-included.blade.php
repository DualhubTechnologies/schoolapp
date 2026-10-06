{{-- What the recommended setup adds; shown in the first-sign-in setup offer (App\Filament\App\Pages\Dashboard). --}}
@php
    $items = [
        [
            'icon' => 'heroicon-o-rectangle-group',
            'title' => 'Classes',
            'text' => 'One class for each year group in the sections you tick. No streams are added: a class works on its own.',
        ],
        [
            'icon' => 'heroicon-o-calendar-days',
            'title' => "{$year} academic year and its three terms",
            'text' => ($officialCalendar
                ? 'Dates from the Ministry of Education and Sports calendar, with '
                : 'Dates follow the usual school calendar (check them against the Ministry\'s), with ')
                ."{$currentTerm} set as the current term.",
            'terms' => $terms,
        ],
        [
            'icon' => 'heroicon-o-book-open',
            'title' => 'Subjects and grading',
            'text' => $isPrimary
                ? 'The national curriculum subjects for each class, with the PLE grading scale and divisions.'
                : 'The national curriculum subjects for each class, UCE and UACE grading scales, and common A-Level combinations.',
        ],
        [
            'icon' => 'heroicon-o-home-modern',
            'title' => 'Day and boarding',
            'text' => 'Learner types, so day scholars and boarders can be charged different fees.',
        ],
        [
            'icon' => 'heroicon-o-banknotes',
            'title' => 'Payroll and finance lists',
            'text' => 'Common staff allowances and deductions, and income and expense categories.',
        ],
    ];
@endphp

<div>
    <p class="text-sm font-medium text-gray-950 dark:text-white">What will be added</p>

    <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $item)
            <li class="flex gap-3 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                </span>
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                    <p class="mt-0.5 text-gray-500 dark:text-gray-400">{{ $item['text'] }}</p>

                    @isset($item['terms'])
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($item['terms'] as $term)
                                <span @class([
                                    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                    'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30' => $term['name'] === $currentTerm,
                                    'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10' => $term['name'] !== $currentTerm,
                                ])>
                                    {{ $term['name'] }}: {{ $term['starts']->format('j M') }} – {{ $term['ends']->format('j M') }}
                                </span>
                            @endforeach
                        </div>
                    @endisset
                </div>
            </li>
        @endforeach
    </ul>
</div>
