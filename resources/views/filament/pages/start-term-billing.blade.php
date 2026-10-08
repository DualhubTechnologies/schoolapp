@php
    $term = $this->term();
    $previous = $this->previousTerm();
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
                Check the termly fees, keep them or update the amounts, then bill every learner. Once-off and optional fees are billed from the Billing page as usual.
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
</x-filament-panels::page>
