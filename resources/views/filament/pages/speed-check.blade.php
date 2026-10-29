@php $rows = $this->rows(); @endphp

<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        How long pages and buttons take on this computer, slowest first. Send this to SchoolHub so we can make the slow ones quicker.
        A page is quick under 1,000 ms (one second).
    </p>

    @if ($rows === [])
        <p class="text-sm">Nothing measured yet. Use SchoolHub as usual, then come back here.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-white/10">
                        <th class="px-4 py-2">Page or action</th>
                        <th class="px-4 py-2 text-right">Average</th>
                        <th class="px-4 py-2 text-right">Slowest</th>
                        <th class="px-4 py-2 text-right">Times</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                            <td class="px-4 py-2">{{ $row['page'] }}</td>
                            <td @class(['px-4 py-2 text-right tabular-nums', 'font-semibold text-red-700' => $row['average'] >= 3000, 'text-amber-700' => $row['average'] >= 1000 && $row['average'] < 3000])>{{ number_format($row['average']) }} ms</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row['slowest']) }} ms</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-filament::button tag="a" :href="$this->whatsappUrl()" target="_blank" icon="heroicon-m-paper-airplane">
                Send to SchoolHub on WhatsApp
            </x-filament::button>
            <x-filament::button color="gray" wire:click="clear" wire:confirm="Clear the measurements and start again?">
                Clear and start again
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
