@php
    $totals = $this->totals();
    $daily = $this->daily();
    $peak = max(1, max(array_column($daily, 'visits') ?: [0]));
    $lists = [
        ['Countries', collect($this->top(['country_code', 'country'])), 'country'],
        ['Cities', collect($this->top(['city', 'country'])), null],
        ['Pages', collect($this->top(['path'])), null],
        ['How they arrived', collect($this->top(['source'])), null],
        ['Devices', collect($this->top(['device'])), null],
    ];
    $unlocated = $this->unlocated();
    $flag = fn (string $code): string => strlen($code) === 2
        ? mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65)
        : '';
@endphp

<x-filament-panels::page>
    <div class="wv-bar">
        <p class="wv-lead">Visits to the public website: the home page, About, Features, Pricing, Help and Contact. Bots, link previews and your own visits are not counted, and no IP address is stored.</p>
        <x-filament::input.wrapper class="wv-period">
            <x-filament::input.select wire:model.live="days" aria-label="Period">
                @foreach (\App\Filament\Pages\WebsiteVisitors::PERIODS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    @unless ($this->locationReady())
        <div class="wv-note">
            <strong>Visitor locations are not switched on yet.</strong>
            Visits are being counted, but countries and cities need MaxMind's free location database on the server:
            <ol>
                <li>Create a free account at <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank" rel="noopener">maxmind.com/en/geolite2/signup</a>, then under <em>Manage license keys</em> generate a licence key.</li>
                <li>On the server, add to <code>.env</code>: <code>MAXMIND_ACCOUNT_ID=…</code> and <code>MAXMIND_LICENSE_KEY=…</code>, then run <code>php artisan config:cache</code>.</li>
                <li>Run <code>php artisan geoip:update</code> once. After that it refreshes itself every week.</li>
            </ol>
            @if ($this->locationKeySet())
                <div>The licence key is set; run <code>php artisan geoip:update</code> on the server to download the database.</div>
            @endif
        </div>
    @endunless

    <div class="wv-kpis">
        <div class="wv-kpi"><span>Visits</span><b>{{ number_format($totals['visits']) }}</b><small>{{ \App\Filament\Pages\WebsiteVisitors::PERIODS[$this->days] ?? '' }}</small></div>
        <div class="wv-kpi"><span>Unique visitors</span><b>{{ number_format($totals['visitors']) }}</b><small>Counted per day</small></div>
        <div class="wv-kpi"><span>Today</span><b>{{ number_format($totals['today']) }}</b><small>{{ number_format($totals['today_visitors']) }} {{ Str::plural('visitor', $totals['today_visitors']) }}</small></div>
        <div class="wv-kpi"><span>Countries</span><b>{{ number_format($totals['countries']) }}</b><small>{{ $unlocated ? number_format($unlocated).' visits without a place' : 'Every visit placed' }}</small></div>
    </div>

    <div class="wv-card">
        <div class="wv-card-head"><strong>Visits per day</strong><span class="wv-muted">Highest: {{ number_format($peak) }}</span></div>
        <div class="wv-chart" role="img" aria-label="Visits per day">
            @foreach ($daily as $day)
                <div class="wv-col" title="{{ $day['date']->format('D j M') }}: {{ $day['visits'] }} {{ Str::plural('visit', $day['visits']) }}, {{ $day['visitors'] }} {{ Str::plural('visitor', $day['visitors']) }}">
                    <span style="height: {{ $day['visits'] ? max(3, round($day['visits'] / $peak * 100)) : 0 }}%"></span>
                </div>
            @endforeach
        </div>
        <div class="wv-axis"><span>{{ $daily[0]['date']->format('j M') }}</span><span>{{ end($daily)['date']->format('j M') }}</span></div>
    </div>

    <div class="wv-grid">
        @foreach ($lists as [$heading, $rows, $kind])
            @php($most = max(1, $rows->max('visits') ?? 1))
            <div class="wv-card">
                <div class="wv-card-head"><strong>{{ $heading }}</strong><span class="wv-muted">Visits · visitors</span></div>
                @forelse ($rows as $row)
                    <div class="wv-row">
                        <div class="wv-row-top">
                            <span>@if ($kind === 'country' && $row['code']){{ $flag($row['code']) }} @endif{{ $row['label'] }}</span>
                            <span class="wv-num">{{ number_format($row['visits']) }} · {{ number_format($row['visitors']) }}</span>
                        </div>
                        <div class="wv-meter"><span style="width: {{ round($row['visits'] / $most * 100) }}%"></span></div>
                    </div>
                @empty
                    <div class="wv-muted wv-empty">{{ in_array($heading, ['Countries', 'Cities'], true) && ! $this->locationReady() ? 'Shown once visitor locations are switched on.' : 'No visits in this period yet.' }}</div>
                @endforelse
            </div>
        @endforeach
    </div>

    <style>
        .wv-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; }
        .wv-lead { color: #475569; font-size: .9rem; margin: 0; max-width: 48rem; }
        .wv-period { min-width: 11rem; }
        .wv-note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: .9rem 1.1rem; color: #78350f; font-size: .875rem; }
        .wv-note ol { margin: .5rem 0 .25rem 1.2rem; list-style: decimal; }
        .wv-note li { margin: .2rem 0; }
        .wv-note a { color: #1d4ed8; text-decoration: underline; }
        .wv-note code { background: #fef3c7; padding: .05rem .3rem; border-radius: 4px; font-size: .8rem; }
        .wv-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: .75rem; }
        .wv-kpi { background: #fff; border: 1px solid #e4e8f0; border-radius: 10px; padding: .9rem 1rem; }
        .wv-kpi span { display: block; font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
        .wv-kpi b { display: block; margin-top: .25rem; font-size: 1.6rem; color: #16233a; font-variant-numeric: tabular-nums; }
        .wv-kpi small { color: #64748b; font-size: .78rem; }
        .wv-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 10px; padding: .9rem 1rem; }
        .wv-card-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin-bottom: .75rem; color: #16233a; }
        .wv-muted { color: #64748b; font-size: .78rem; }
        .wv-chart { display: flex; align-items: flex-end; gap: 2px; height: 9rem; border-bottom: 1px solid #e2e8f0; }
        .wv-col { flex: 1; height: 100%; display: flex; align-items: flex-end; }
        .wv-col span { display: block; width: 100%; background: #2563eb; border-radius: 3px 3px 0 0; min-height: 0; }
        .wv-col:hover span { background: #1d4ed8; }
        .wv-axis { display: flex; justify-content: space-between; margin-top: .3rem; color: #64748b; font-size: .72rem; }
        .wv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); gap: .75rem; }
        .wv-row { padding: .35rem 0; }
        .wv-row-top { display: flex; justify-content: space-between; gap: .75rem; font-size: .85rem; color: #1e293b; }
        .wv-num { color: #475569; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .wv-meter { margin-top: .25rem; height: .3rem; background: #eef2f7; border-radius: 999px; overflow: hidden; }
        .wv-meter span { display: block; height: 100%; background: #60a5fa; border-radius: 999px; }
        .wv-empty { padding: .5rem 0; }
    </style>
</x-filament-panels::page>
