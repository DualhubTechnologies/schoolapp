<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Switch off what your school does not use, and it leaves the menu and dashboard for everyone.
            Nothing is deleted: switch a module back on and its records are all there.
        </p>

        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-m-check">
            Save
        </x-filament::button>
    </form>
</x-filament-panels::page>
