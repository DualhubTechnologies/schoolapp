<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-play-circle" icon-color="warning">
        <x-slot name="heading">{{ $this->term()?->label() }} has started: learners have not been billed yet</x-slot>
        <x-slot name="description">Keep last term's fees or update them for this term, then bill every learner in one step. Earlier terms keep their fees on record.</x-slot>

        <x-filament::button tag="a" :href="$this->url()" icon="heroicon-m-arrow-right" icon-position="after">
            Set fees and bill the term
        </x-filament::button>
    </x-filament::section>
</x-filament-widgets::widget>
