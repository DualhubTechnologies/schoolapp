@php
    $checks = $this->checks();
    $problems = collect($checks)->where('status', '!=', 'ok')->count();
    $tone = ['ok' => ['#15803d', '#f0fdf4', 'Working'], 'warning' => ['#b45309', '#fffbeb', 'Check'], 'danger' => ['#b91c1c', '#fef2f2', 'Not working']];
@endphp

<x-filament-panels::page>
    <section class="sh-server" wire:poll.30s>
        <div class="sh-server-head">
            <div>
                <h2>Server</h2>
                <div class="sh-muted">Live readings from this server, refreshed every 30 seconds · last read {{ now()->format('H:i:s') }}</div>
            </div>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-path" wire:click="$refresh">
                <span wire:loading.remove wire:target="$refresh">Refresh now</span>
                <span wire:loading wire:target="$refresh">Reading…</span>
            </x-filament::button>
        </div>

        @php($readings = $this->serverReadings())
        @if ($readings === [])
            <div class="sh-muted sh-none">Server readings are not available here (they need a Linux server).</div>
        @else
            <div class="sh-meters">
                @foreach ($readings as $reading)
                    @php([$color, $bg, $word] = $tone[$reading['status']])
                    <div class="sh-meter" style="border-top-color: {{ $color }}">
                        <div class="sh-meter-head">
                            <span>{{ $reading['label'] }}</span>
                            @if ($reading['status'] !== 'ok')
                                <span class="sh-badge" style="color: {{ $color }}; background: {{ $bg }}">{{ $reading['status'] === 'danger' ? 'High' : 'Watch' }}</span>
                            @endif
                        </div>
                        <div class="sh-meter-value">{{ $reading['value'] }}</div>
                        @if ($reading['percent'] !== null)
                            <div class="sh-bar" role="progressbar" aria-valuenow="{{ $reading['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                <span style="width: {{ max(2, $reading['percent']) }}%; background: {{ $color }}"></span>
                            </div>
                        @endif
                        <div class="sh-meter-detail">{{ $reading['detail'] }}</div>
                        @if ($reading['fix'])
                            <div class="sh-fix"><b>What to do:</b> {{ $reading['fix'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <p class="sh-lead">
        {{ $problems === 0 ? 'Everything that runs in the background is working.' : $problems.' '.Str::plural('item needs', $problems).' attention. Each shows what to change on the server.' }}
        Refresh the page after a change.
    </p>

    <div class="sh-list">
        @foreach ($checks as $check)
            @php([$color, $bg, $word] = $tone[$check['status']])
            <div class="sh-row" style="border-left-color: {{ $color }}">
                <div class="sh-head">
                    <strong>{{ $check['label'] }}</strong>
                    <span class="sh-badge" style="color: {{ $color }}; background: {{ $bg }}">{{ $word }}</span>
                </div>
                <div class="sh-summary">{{ $check['summary'] }}</div>
                @if ($check['fix'] && $check['status'] !== 'ok')
                    <div class="sh-fix"><b>Fix:</b> {{ $check['fix'] }}</div>
                @endif
            </div>
        @endforeach
    </div>

    <style>
        .sh-lead { color: #475569; font-size: .92rem; margin: 0; }
        .sh-list { display: grid; gap: .75rem; max-width: 52rem; }
        .sh-row { background: #fff; border: 1px solid #e4e8f0; border-left: 4px solid; border-radius: 10px; padding: .85rem 1rem; }
        .sh-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
        .sh-head strong { color: #16233a; font-size: .95rem; }
        .sh-badge { font-size: .72rem; font-weight: 700; letter-spacing: .03em; padding: .2rem .6rem; border-radius: 999px; white-space: nowrap; }
        .sh-summary { margin-top: .3rem; color: #334155; font-size: .88rem; }
        .sh-server { max-width: 52rem; }
        .sh-server-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: .75rem; }
        .sh-server-head h2 { font-size: 1.05rem; font-weight: 700; color: #16233a; margin: 0; }
        .sh-muted { color: #64748b; font-size: .8rem; }
        .sh-none { padding: 1rem; background: #fff; border: 1px dashed #cbd5e1; border-radius: 10px; }
        .sh-meters { display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: .75rem; }
        .sh-meter { background: #fff; border: 1px solid #e4e8f0; border-top: 3px solid; border-radius: 10px; padding: .8rem .9rem; }
        .sh-meter-head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
        .sh-meter-value { margin-top: .35rem; font-size: 1.05rem; font-weight: 700; color: #16233a; font-variant-numeric: tabular-nums; }
        .sh-bar { margin-top: .5rem; height: .45rem; background: #eef2f7; border-radius: 999px; overflow: hidden; }
        .sh-bar span { display: block; height: 100%; border-radius: 999px; transition: width .4s; }
        .sh-meter-detail { margin-top: .45rem; color: #475569; font-size: .78rem; }
        .sh-fix { margin-top: .4rem; color: #475569; font-size: .82rem; background: #f8fafc; border-radius: 6px; padding: .45rem .6rem; }
    </style>
</x-filament-panels::page>
