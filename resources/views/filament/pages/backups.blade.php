@php($backups = $this->backups())

<x-filament-panels::page>
    <div class="bk-note">
        <strong>Keep a copy away from this computer.</strong>
        All of your school's records are on this computer. If it is stolen, breaks or gets a virus, only a copy kept somewhere else can bring them back.
        Click <em>Back up now</em>, then <em>Download</em> each file and save it on a flash disk (or another computer) at least once a week.
        A backup is also made here every night.
    </div>

    <div class="bk-card">
        @forelse ($backups as $backup)
            <div class="bk-row" wire:key="bk-{{ $backup['name'] }}">
                <div>
                    <div class="bk-kind">{{ $backup['kind'] }}</div>
                    <div class="bk-meta">{{ $backup['made']->format('l j M Y, g:i a') }} · {{ $backup['size'] }}</div>
                </div>
                <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="download('{{ $backup['name'] }}')">Download</x-filament::button>
            </div>
        @empty
            <div class="bk-empty">No backups yet. Click <em>Back up now</em> to make the first one.</div>
        @endforelse
    </div>

    <style>
        .bk-note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: .9rem 1.1rem; color: #1e3a8a; font-size: .875rem; max-width: 52rem; }
        .bk-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 10px; max-width: 52rem; }
        .bk-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .8rem 1rem; border-bottom: 1px solid #f1f5f9; }
        .bk-row:last-child { border-bottom: 0; }
        .bk-kind { font-weight: 600; color: #16233a; font-size: .9rem; }
        .bk-meta { color: #64748b; font-size: .8rem; margin-top: .1rem; }
        .bk-empty { padding: 1.25rem 1rem; color: #64748b; font-size: .875rem; }
    </style>
</x-filament-panels::page>
