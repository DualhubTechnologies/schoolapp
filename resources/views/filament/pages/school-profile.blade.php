<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                        School Profile
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your school information, branding, and settings.
                    </p>
                </div>
            </div>
        </div>

        {{ $this->form }}

        <div class="sticky bottom-4 z-10 rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        Save your changes
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Update school profile information.
                    </p>
                </div>

                <x-filament::button type="submit" icon="heroicon-m-check">
                    Save Changes
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>