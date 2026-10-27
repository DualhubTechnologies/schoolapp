<x-filament-panels::page>
    <div class="wa-grid">
        <div class="wa-card">
            <h3>Work offline on a school computer, kept in step with this account</h3>
            <p>Install SchoolHub for Windows on a school computer and link it here. Staff can then work on that computer without internet, and everything syncs both ways whenever it is online: learners, fees and payments, expenses and marks.</p>
            <ul class="wa-list">
                <li>If a record was changed in both places, the latest change is kept.</li>
                <li>Possible duplicate learners, similar payments (same amount, same day) and deleted payments are listed on the computer for someone to decide.</li>
            </ul>
        </div>

        <div class="wa-card">
            <h3>Link a computer</h3>
            @if ($linkCode)
                <p>In SchoolHub for Windows, open <b>Settings → Sync with online</b> and type this code within the next hour:</p>
                <div class="wa-code">{{ $linkCode }}</div>
                <p class="wa-muted">The code works once, for {{ auth()->user()->school?->name }}.</p>
            @else
                <p>Make a code, then type it into SchoolHub for Windows under <b>Settings → Sync with online</b>.</p>
                <x-filament::button wire:click="makeCode" icon="heroicon-m-link">Make a link code</x-filament::button>
            @endif
        </div>
    </div>

    <div class="wa-card">
        <h3>Linked computers</h3>
        @forelse ($this->computers() as $computer)
            <div class="wa-row" wire:key="computer-{{ $computer->id }}">
                <div>
                    <b>{{ $computer->name }}</b>
                    <span>Linked {{ $computer->linked_at?->format('j M Y') }}{{ $computer->created_by ? ' by '.$computer->created_by : '' }} · {{ $computer->last_synced_at ? 'last synced '.$computer->last_synced_at->diffForHumans() : 'not synced yet' }}</span>
                </div>
                <x-filament::button size="sm" color="gray" wire:click="remove({{ $computer->id }})" wire:confirm="Remove {{ $computer->name }}? It stops syncing straight away.">Remove</x-filament::button>
            </div>
        @empty
            <p class="wa-muted">No computer linked yet.</p>
        @endforelse
    </div>

    <style>
        .wa-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
        .wa-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1rem 1.2rem; }
        .wa-card h3 { font-weight: 700; color: #16233a; margin: 0 0 .4rem; }
        .wa-card p { color: #475569; font-size: .875rem; margin: .4rem 0 .7rem; }
        .wa-list { margin: .5rem 0 0 1.1rem; list-style: disc; color: #475569; font-size: .875rem; }
        .wa-code { font-family: ui-monospace, Consolas, monospace; font-size: 2rem; font-weight: 800; letter-spacing: .15em; color: #16233a; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: .6rem 1rem; display: inline-block; }
        .wa-muted { color: #64748b !important; font-size: .8rem !important; }
        .wa-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid #f1f5f9; }
        .wa-row:last-child { border-bottom: 0; }
        .wa-row b { display: block; color: #16233a; }
        .wa-row span { font-size: .8rem; color: #64748b; }
        .dark .wa-card { background: rgb(24 24 27); border-color: rgb(63 63 70); }
        .dark .wa-card h3, .dark .wa-row b, .dark .wa-code { color: #f4f4f5; }
        .dark .wa-card p { color: #d4d4d8; }
        .dark .wa-code { background: rgb(39 39 42); }
    </style>
</x-filament-panels::page>
