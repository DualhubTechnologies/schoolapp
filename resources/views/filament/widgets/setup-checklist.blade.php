@php
    $steps = $this->steps();
    $done = collect($steps)->where('done', true)->count();
    $total = count($steps);
    $percent = round($done / $total * 100);
    $next = collect($steps)->firstWhere('done', false);
@endphp

<x-filament-widgets::widget class="sh-setup-host">
    <div class="sh-setup-pop" x-data="{ open: false, hidden: false }" x-show="! hidden" x-transition.opacity role="dialog" aria-label="Finish setting up">
        <div class="sh-setup-head">
            <div>
                <strong>Finish setting up</strong>
                <span>{{ $done }} of {{ $total }} steps done</span>
            </div>
            <button type="button" class="sh-setup-close" title="Hide until next sign-in" aria-label="Hide until next sign-in"
                x-on:click="hidden = true; $wire.dismiss()">
                <x-filament::icon icon="heroicon-m-x-mark" />
            </button>
        </div>

        <div class="sh-setup-bar"><span style="width: {{ $percent }}%"></span></div>

        @if ($next)
            <div class="sh-setup-next">
                <div>
                    <small>Next step</small>
                    <b>{{ $next['title'] }}</b>
                </div>
                <x-filament::button tag="a" :href="$next['url']" size="sm">Start</x-filament::button>
            </div>
        @endif

        <ol class="sh-setup" x-show="open" x-cloak>
            @foreach ($steps as $step)
                <li @class(['is-done' => $step['done'], 'is-next' => $step === $next])>
                    <span class="sh-setup-mark">
                        @if ($step['done'])
                            <x-filament::icon icon="heroicon-m-check" />
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>
                    <div class="sh-setup-body">
                        @if ($step['done'])
                            <strong>{{ $step['title'] }}</strong>
                        @else
                            <a href="{{ $step['url'] }}">{{ $step['title'] }}</a>
                        @endif
                        <span>{{ $step['text'] }}</span>
                    </div>
                </li>
            @endforeach
        </ol>

        <button type="button" class="sh-setup-toggle" x-on:click="open = ! open"
            x-text="open ? 'Hide steps' : 'See all steps'">See all steps</button>
    </div>

    <style>
        /* A card in the dashboard's flow, right under the greeting (it used to float over the charts). */
        .sh-setup-pop {
            display: grid; grid-template-columns: minmax(0, 15rem) minmax(0, 1fr) auto; grid-template-areas: "head next toggle" "bar bar bar" "list list list";
            align-items: center; column-gap: 1.25rem; padding: 1rem 1.15rem .9rem 1.25rem; background: #fff; border: 1px solid #e3e9f2; border-left: 3px solid var(--sh-gold, #c4973a); border-radius: 12px; box-shadow: 0 1px 2px rgba(13, 31, 56, .04);
        }
        .sh-setup-head { grid-area: head; display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
        .sh-setup-next { grid-area: next; }
        .sh-setup-toggle { grid-area: toggle; }
        .sh-setup-bar { grid-area: bar; }
        .sh-setup { grid-area: list; }
        @media (max-width: 767px) {
            .sh-setup-pop { grid-template-columns: minmax(0, 1fr); grid-template-areas: "head" "bar" "next" "toggle" "list"; }
        }
        .sh-setup-head strong { display: block; font-family: var(--sh-serif, Georgia, serif); font-size: 1rem; color: #13294b; }
        .sh-setup-head span { font-size: .8rem; color: #64748b; }
        .sh-setup-close { flex: none; display: grid; place-items: center; width: 1.9rem; height: 1.9rem; margin: -.35rem -.35rem 0 0; border-radius: 50%; color: #64748b; }
        .sh-setup-close:hover { background: #f1f5f9; color: #13294b; }
        .sh-setup-close svg { width: 1.1rem; height: 1.1rem; }
        .sh-setup-bar { height: .3rem; border-radius: 99px; background: #eef2f7; overflow: hidden; margin: .85rem 0 0; }
        .sh-setup-bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--sh-gold, #c4973a), var(--sh-gold-light, #e2c47e)); }
        .sh-setup-next { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem .75rem; border-radius: .6rem; background: var(--sh-gold-soft, #fbf5e6); border: 1px solid var(--sh-gold-line, #ead8ab); }
        .sh-setup-next small { display: block; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
        .sh-setup-next b { font-size: .88rem; color: #13294b; }
        .sh-setup { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: .25rem 1rem; margin-top: .75rem; }
        .sh-setup li { display: flex; align-items: flex-start; gap: .65rem; padding: .5rem .25rem; }
        .sh-setup li.is-done { opacity: .6; }
        .sh-setup-mark { flex: none; display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: #eef2f7; color: #475569; font-size: .72rem; font-weight: 700; }
        .sh-setup li.is-done .sh-setup-mark { background: #dcfce7; color: #166534; }
        .sh-setup li.is-next .sh-setup-mark { background: var(--sh-navy-700, #1b3a63); color: #fff; }
        .sh-setup-mark svg { width: .9rem; height: .9rem; }
        .sh-setup-body { flex: 1; min-width: 0; }
        .sh-setup-body strong, .sh-setup-body a { display: block; font-size: .85rem; font-weight: 600; color: #13294b; }
        .sh-setup-body a:hover { color: #2563eb; text-decoration: underline; }
        .sh-setup-body span { display: block; font-size: .76rem; color: #64748b; }
        .sh-setup li.is-done strong { text-decoration: line-through; }
        .sh-setup-toggle { display: block; justify-self: end; font-size: .8rem; font-weight: 600; color: var(--sh-navy-700, #1b3a63); white-space: nowrap; }
        .sh-setup-toggle:hover { text-decoration: underline; }
        @media (max-width: 767px) { .sh-setup-toggle { justify-self: center; margin-top: .65rem; } .sh-setup-next { margin-top: .75rem; } }
        .dark .sh-setup-pop { background: #111827; border-color: #1f2937; }
        .dark .sh-setup-head strong, .dark .sh-setup-next b, .dark .sh-setup-body strong, .dark .sh-setup-body a { color: #f1f5f9; }
        .dark .sh-setup-next { background: #172036; border-color: #1e3a8a; }
        .dark .sh-setup-bar, .dark .sh-setup-mark { background: #1f2937; }
    </style>
</x-filament-widgets::widget>
