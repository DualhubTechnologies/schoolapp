@php
    $checks = $this->checks();
    $problems = collect($checks)->where('status', '!=', 'ok')->count();
    $tone = ['ok' => ['#15803d', '#f0fdf4', 'Working'], 'warning' => ['#b45309', '#fffbeb', 'Check'], 'danger' => ['#b91c1c', '#fef2f2', 'Not working']];
@endphp

<x-filament-panels::page>
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
        .sh-fix { margin-top: .4rem; color: #475569; font-size: .82rem; background: #f8fafc; border-radius: 6px; padding: .45rem .6rem; }
    </style>
</x-filament-panels::page>
