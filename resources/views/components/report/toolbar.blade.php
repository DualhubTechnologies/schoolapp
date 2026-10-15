{{-- The report's filters (the slot), with Print and Download Excel at the end. Not printed. --}}
<div class="rpt-toolbar rpt-no-print">
    <div class="rpt-filters">{{ $slot }}</div>
    <div class="rpt-actions">
        <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="downloadExcel">Excel</x-filament::button>
        <x-filament::button icon="heroicon-o-printer" onclick="window.print()">Print</x-filament::button>
    </div>
</div>
