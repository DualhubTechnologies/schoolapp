<x-filament-widgets::widget>
    <div class="shd-kpis">
        @foreach ($this->cards() as $card)
            <{{ $card['url'] ? 'a' : 'div' }} @if ($card['url']) href="{{ $card['url'] }}" @endif class="shd-kpi shd-tone-{{ $card['tone'] }}">
                <div class="shd-kpi-head">
                    <span class="shd-kpi-icon"><x-filament::icon :icon="$card['icon']" /></span>
                    <span class="shd-kpi-label">{{ $card['label'] }}</span>
                    @if ($card['url'])
                        <svg class="shd-kpi-go" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 0 0 1.06 0l7.22-7.22v5.69a.75.75 0 0 0 1.5 0v-7.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0 0 1.5h5.69l-7.22 7.22a.75.75 0 0 0 0 1.06Z" clip-rule="evenodd"/></svg>
                    @endif
                </div>
                <div class="shd-kpi-value">{{ $card['value'] }}</div>
                @if ($card['progress'] !== null)
                    <div class="shd-kpi-bar"><span style="width: {{ $card['progress'] }}%"></span></div>
                @endif
                @if ($card['sub'])
                    <div class="shd-kpi-sub">
                        @if ($card['trend'] === 'up')
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="shd-kpi-trend"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/></svg>
                        @endif
                        {{ $card['sub'] }}
                    </div>
                @endif
            </{{ $card['url'] ? 'a' : 'div' }}>
        @endforeach
    </div>
</x-filament-widgets::widget>
