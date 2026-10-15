@php
    $shown = array_slice($findings, 0, \App\Filament\App\Widgets\Insights::SHOWN);
    $more = count($findings) - count($shown);
@endphp

<x-filament-widgets::widget class="shi-widget">
    @if ($findings)
        <div x-data="{ open: true }" x-show="open" x-cloak @keydown.escape.window="open = false" class="shi-pop" role="dialog" aria-modal="true" aria-labelledby="shi-pop-title">
            <div class="shi-pop-backdrop" @click="open = false"></div>
            <section class="shi-pop-card">
                <header class="shi-pop-head">
                    <span class="shi-pop-badge"><x-filament::icon icon="heroicon-o-sparkles" /></span>
                    <div>
                        <h3 id="shi-pop-title">AI Insights</h3>
                        <p>What your school's records point out today</p>
                    </div>
                    <button type="button" class="shi-pop-x" @click="open = false" aria-label="Close">
                        <x-filament::icon icon="heroicon-m-x-mark" />
                    </button>
                </header>

                <div class="shi-pop-body">
                    @include('filament.app.partials.insights-list', ['findings' => $shown])
                </div>

                <footer class="shi-pop-foot">
                    <span>Shown once a day. Find them any time under <b>AI Insights</b> in the menu.</span>
                    <div class="shi-pop-actions">
                        <x-filament::button color="gray" @click="open = false">Close</x-filament::button>
                        <x-filament::button tag="a" :href="\App\Filament\App\Pages\AiInsights::getUrl()">
                            {{ $more > 0 ? "See all ".count($findings) : 'Open AI Insights' }}
                        </x-filament::button>
                    </div>
                </footer>
            </section>
        </div>
    @endif

    <style>
        /* The widget takes no room on the dashboard: only the pop-up shows. */
        .fi-wi-widget.shi-widget, .shi-widget { display: contents; }
        .shi-pop { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 1rem; }
        .shi-pop-backdrop { position: absolute; inset: 0; background: rgba(13, 31, 56, .45); }
        .shi-pop-card { position: relative; width: 100%; max-width: 34rem; max-height: calc(100vh - 2rem); display: flex; flex-direction: column; background: #fff; border-radius: 16px; border-top: 3px solid var(--sh-gold, #c9a227); box-shadow: 0 24px 60px rgba(13, 31, 56, .3); }
        .shi-pop-head { display: flex; align-items: center; gap: .8rem; padding: 1.1rem 1.25rem .6rem; }
        .shi-pop-badge { flex: none; display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: #12294a; color: #f3d77a; }
        .shi-pop-badge svg { width: 1.25rem; height: 1.25rem; }
        .shi-pop-head div { flex: 1; min-width: 0; }
        .shi-pop-head h3 { font-family: var(--sh-serif, Georgia, serif); font-size: 1.15rem; font-weight: 700; color: #12294a; }
        .shi-pop-head p { font-size: .8rem; color: #64748b; }
        .shi-pop-x { flex: none; align-self: flex-start; padding: .3rem; border-radius: .5rem; color: #64748b; }
        .shi-pop-x:hover { background: #f1f5f9; color: #12294a; }
        .shi-pop-x svg { width: 1.25rem; height: 1.25rem; }
        .shi-pop-body { overflow-y: auto; padding: 0 1.25rem; }
        .shi-pop-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem 1rem; flex-wrap: wrap; padding: .85rem 1.25rem 1.1rem; border-top: 1px solid #f1f5f9; }
        .shi-pop-foot > span { font-size: .75rem; color: #64748b; flex: 1 1 14rem; }
        .shi-pop-actions { display: flex; gap: .5rem; margin-left: auto; }
    </style>
</x-filament-widgets::widget>
