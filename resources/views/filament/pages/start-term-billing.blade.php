@php
    $term = $this->term();
    $previous = $this->previousTerm();
    $checks = $this->checks();
    $names = fn ($learners) => $learners->take(8)->map(fn ($s) => $s->name.' ('.$s->schoolClass?->name.')')->implode(', ')
        .($learners->count() > 8 ? ' and '.($learners->count() - 8).' more' : '');
    $students = \App\Filament\App\Resources\Students\StudentResource::class;
@endphp

<x-filament-panels::page>
    @if (! $term)
        <x-filament::section>
            <x-slot name="heading">No current term</x-slot>
            Set the current term under Academics → Terms, then come back to bill it.
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $term->label() }}</x-slot>
            <x-slot name="description">
                {{ $previous ? 'Last term was '.$previous->label().'.' : '' }}
                Check the termly fees, keep them or update the amounts, then bill every learner. New learners are billed their admission fees at the same time; charges for some learners only (UNEB registration, trips) are billed from the Billing page.
            </x-slot>

            @if (blank($this->data['fees'] ?? null))
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    No termly fees are set up yet, so there is nothing to bill.
                    First add what each class pays, then come back here.
                </p>
                <div class="mt-4">
                    <x-filament::button tag="a" :href="\App\Filament\App\Resources\FeeStructures\FeeStructureResource::getUrl('create')" icon="heroicon-m-plus">
                        Add the fees
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        @if ($checks && filled($this->data['fees'] ?? null))
            <x-filament::section icon="heroicon-o-clipboard-document-check" compact>
                <x-slot name="heading">Before you bill</x-slot>

                <ul class="sh-bill-checks">
                    @if ($checks['missingResidency']->isNotEmpty())
                        <li class="is-warn">
                            <b>{{ $checks['missingResidency']->count() }} {{ str('learner')->plural($checks['missingResidency']->count()) }} with no Day or Boarding set</b>
                            will miss the fees that depend on it, such as tuition: {{ $names($checks['missingResidency']) }}.
                            Set it on their profile first.
                        </li>
                    @endif
                    @if ($checks['classesWithoutFees']->isNotEmpty())
                        <li class="is-warn">
                            <b>No fees are set for {{ $checks['classesWithoutFees']->join(', ', ' and ') }}</b>, so their learners will not be billed.
                            <a href="{{ \App\Filament\App\Resources\FeeStructures\FeeStructureResource::getUrl('create') }}">Add their fees</a>.
                        </li>
                    @endif
                    @if ($checks['newLearners']->isNotEmpty())
                        <li class="is-info">
                            <b>{{ $checks['newLearners']->count() }} new {{ str('learner')->plural($checks['newLearners']->count()) }} will pay the admission fees:</b>
                            {{ $names($checks['newLearners']) }}.
                            <a href="{{ $students::getUrl('index', ['filters' => ['is_new_learner' => ['value' => 1]]]) }}">Review new learners</a> if any of them was already at the school.
                        </li>
                    @endif
                    @if ($checks['missingResidency']->isEmpty() && $checks['classesWithoutFees']->isEmpty())
                        <li class="is-ok"><b>Every learner has fees set and Day or Boarding chosen.</b></li>
                    @endif
                </ul>
            </x-filament::section>
        @endif

        @if (filled($this->data['fees'] ?? null))
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit" icon="heroicon-m-check">
                    Save and continue
                </x-filament::button>
                <x-filament::link :href="\App\Filament\Pages\FeeStructureSheet::getUrl()" icon="heroicon-m-document-text">
                    See the fee structure of any term
                </x-filament::link>
            </div>
        </form>
        @endif
    @endif
    <style>
        .sh-bill-checks { display: grid; gap: .6rem; font-size: .875rem; line-height: 1.45; }
        .sh-bill-checks li { padding: .6rem .8rem; border-radius: .6rem; border: 1px solid; }
        .sh-bill-checks li.is-warn { background: #fffbeb; border-color: #fcd34d; color: #78350f; }
        .sh-bill-checks li.is-info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
        .sh-bill-checks li.is-ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .sh-bill-checks a { font-weight: 600; text-decoration: underline; }
        .dark .sh-bill-checks li.is-warn { background: rgba(245, 158, 11, .12); color: #fcd34d; }
        .dark .sh-bill-checks li.is-info { background: rgba(59, 130, 246, .12); color: #bfdbfe; }
        .dark .sh-bill-checks li.is-ok { background: rgba(16, 185, 129, .12); color: #a7f3d0; }
    </style>
</x-filament-panels::page>
