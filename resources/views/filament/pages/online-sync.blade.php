@php
    $st = $this->status();
    $reviews = $st['linked'] ? $this->reviews() : [];
@endphp

<x-filament-panels::page>
    @if (! $st['linked'])
        <div class="os-grid">
            <div class="os-card">
                <h3>Work offline and online, kept in step</h3>
                <p>Link this computer to {{ config('app.name') }} online and everything entered here (learners, fees, payments, expenses, marks) goes online, and what is entered online comes here. Staff can work on this computer without internet, or online from a phone.</p>
                <ul class="os-list">
                    <li>Syncs on its own every few minutes whenever there is internet, or press <b>Sync now</b>.</li>
                    <li>If a record was changed in both places, the latest change is kept.</li>
                    <li>Possible duplicates, similar payments and deleted payments are listed here for you to decide.</li>
                </ul>
            </div>

            <div class="os-card">
                <h3>Link this computer</h3>
                @if ($this->canManage())
                    <p>Online, open <b>Settings → Windows app</b> and click <b>Make a link code</b>. Type the code here:</p>
                    <form wire:submit="link">
                        <input type="text" wire:model="code" class="os-code" placeholder="XXXX-XXXX" aria-label="Link code"
                            autocomplete="off" spellcheck="false" autocapitalize="characters"
                            x-data
                            x-on:input="
                                const plain = $el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
                                $el.value = (plain.match(/.{1,4}/g) || []).join('-');
                                $el.dispatchEvent(new Event('change'));
                            ">
                        @error('code')<div class="os-error">{{ $message }}</div>@enderror
                        <x-filament::button type="submit" icon="heroicon-m-link" class="os-submit">
                            <span wire:loading.remove wire:target="link">Link and sync</span>
                            <span wire:loading wire:target="link">Linking and syncing…</span>
                        </x-filament::button>
                    </form>
                    <p class="os-muted">Needs the internet (phone data is enough). The first sync can take a few minutes for a big school.</p>
                @else
                    <p>Ask the school administrator to link this computer.</p>
                @endif
            </div>
        </div>
    @else
        <div class="os-status">
            <div>
                <div class="os-label">Linked to {{ $st['school'] }} online</div>
                <p>
                    @if ($st['last'])
                        Last synced {{ $st['last']->diffForHumans() }}
                        ({{ $st['result']['received'] ?? 0 }} received, {{ $st['result']['sent'] ?? 0 }} sent).
                    @else
                        Not synced yet.
                    @endif
                    Syncs on its own every {{ config('sync.every_minutes') }} minutes while there is internet.
                </p>
            </div>
            <div class="os-actions">
                <x-filament::button wire:click="syncNow" icon="heroicon-m-arrow-path">
                    <span wire:loading.remove wire:target="syncNow">Sync now</span>
                    <span wire:loading wire:target="syncNow">Syncing…</span>
                </x-filament::button>
                @if ($this->canManage())
                    <x-filament::button color="gray" wire:click="unlink" wire:confirm="Unlink this computer? It stops syncing; everything on it stays. You can link it again later with a new code.">
                        Unlink
                    </x-filament::button>
                @endif
            </div>
        </div>

        @if (($st['result']['waiting'] ?? 0) > 0 && $reviews === [])
            <p class="os-muted">{{ $st['result']['waiting'] }} {{ Str::plural('change', $st['result']['waiting']) }} from online are waiting for records they depend on, and will be saved at a later sync.</p>
        @endif

        <h2 class="os-heading">
            @if ($reviews === [])
                Nothing needs your decision.
            @else
                {{ count($reviews) }} {{ Str::plural('thing', count($reviews)) }} {{ count($reviews) === 1 ? 'needs' : 'need' }} your decision
            @endif
        </h2>

        @foreach ($reviews as $review)
            <div class="os-review" wire:key="review-{{ $review['id'] }}">
                <h3>{{ $review['title'] }}</h3>
                <div class="os-compare">
                    <div>
                        <div class="os-side">On this computer</div>
                        @if ($review['here'])
                            @foreach ($review['here'] as $label => $value)
                                <div class="os-row"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                            @endforeach
                        @else
                            <p class="os-muted">{{ $review['kind'] === 'deleted_here' ? 'Deleted on this computer.' : 'Not on this computer.' }}</p>
                        @endif
                    </div>
                    <div>
                        <div class="os-side">Online</div>
                        @if ($review['online'])
                            @foreach ($review['online'] as $label => $value)
                                <div class="os-row"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                            @endforeach
                        @else
                            <p class="os-muted">{{ $review['kind'] === 'deleted_there' ? 'Deleted online.' : ($review['kind'] === 'deleted_here' ? 'Details come at the next sync.' : '-') }}</p>
                        @endif
                    </div>
                </div>
                <div class="os-choices">
                    @foreach ($review['options'] as $decision => $label)
                        <x-filament::button size="sm" :color="$loop->first ? 'primary' : 'gray'" wire:click="decide({{ $review['id'] }}, '{{ $decision }}')" wire:loading.attr="disabled">
                            {{ $label }}
                        </x-filament::button>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    <style>
        .os-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
        .os-card, .os-review { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1rem 1.2rem; }
        .os-card h3, .os-review h3 { font-weight: 700; color: #16233a; margin: 0 0 .4rem; }
        .os-card p { color: #475569; font-size: .875rem; margin: .4rem 0; }
        .os-list { margin: .5rem 0 0 1.1rem; list-style: disc; color: #475569; font-size: .875rem; }
        .os-list li { margin: .25rem 0; }
        .os-code { width: 100%; font-family: ui-monospace, Consolas, monospace; font-size: 1.25rem; letter-spacing: .12em; text-transform: uppercase; border: 1px solid #cbd5e1; border-radius: 8px; padding: .65rem .8rem; }
        .os-error { color: #b91c1c; font-size: .85rem; margin-top: .35rem; }
        .os-submit { margin-top: .6rem; }
        .os-muted { color: #64748b !important; font-size: .8rem !important; }
        .os-status { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; border: 1px solid #15803d; border-left-width: 5px; background: #f0fdf4; border-radius: 12px; padding: 1rem 1.2rem; }
        .os-status p { margin: .3rem 0 0; color: #334155; font-size: .9rem; }
        .os-label { font-weight: 800; font-size: 1.05rem; color: #15803d; }
        .os-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .os-heading { font-weight: 700; color: #16233a; font-size: 1rem; }
        .os-compare { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; margin: .6rem 0 .9rem; }
        .os-side { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: .3rem; }
        .os-row { display: flex; justify-content: space-between; gap: 1rem; padding: .3rem 0; border-bottom: 1px solid #f1f5f9; font-size: .875rem; }
        .os-row span { color: #64748b; }
        .os-row b { color: #16233a; text-align: right; }
        .os-choices { display: flex; flex-wrap: wrap; gap: .5rem; }
        .dark .os-card, .dark .os-review { background: rgb(24 24 27); border-color: rgb(63 63 70); }
        .dark .os-card h3, .dark .os-review h3, .dark .os-heading, .dark .os-row b { color: #f4f4f5; }
        .dark .os-status { background: rgb(20 83 45 / .25); }
        .dark .os-status p, .dark .os-card p { color: #d4d4d8; }
    </style>
</x-filament-panels::page>
