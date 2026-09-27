@php
    $steps = $this->steps();
    $done = collect($steps)->where('done', true)->count();
    $total = count($steps);
    $next = collect($steps)->firstWhere('done', false);
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Finish setting up {{ auth()->user()->school?->name }}</x-slot>
        <x-slot name="description">{{ round($done / $total * 100) }}% ready — {{ $done }} of {{ $total }} steps done.{{ $next ? ' Next: ' . $next['title'] . '.' : '' }} This list goes away once every step is complete.</x-slot>

        <div class="sh-setup-bar"><span style="width: {{ round($done / $total * 100) }}%"></span></div>

        <ol class="sh-setup">
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
                        <strong>{{ $step['title'] }}</strong>
                        <span>{{ $step['text'] }}</span>
                    </div>
                    @unless ($step['done'])
                        <x-filament::button tag="a" :href="$step['url']" size="sm" :outlined="$step !== $next">
                            {{ $step === $next ? 'Start' : 'Open' }}
                        </x-filament::button>
                    @endunless
                </li>
            @endforeach
        </ol>
    </x-filament::section>

    <style>
        .sh-setup-bar { height: .4rem; border-radius: 99px; background: #eef2f7; overflow: hidden; margin-bottom: 1rem; }
        .sh-setup-bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #1a5fa8, #f59e0b); }
        .sh-setup { display: grid; gap: .5rem; }
        .sh-setup li { display: flex; align-items: center; gap: .85rem; padding: .7rem .85rem; border: 1px solid #e3e9f2; border-radius: .75rem; }
        .sh-setup li.is-next { border-color: #93c5fd; background: #f5f9ff; }
        .sh-setup li.is-done { opacity: .6; }
        .sh-setup-mark { flex: none; display: grid; place-items: center; width: 1.8rem; height: 1.8rem; border-radius: 50%; background: #eef2f7; color: #475569; font-size: .8rem; font-weight: 700; }
        .sh-setup li.is-done .sh-setup-mark { background: #dcfce7; color: #166534; }
        .sh-setup li.is-next .sh-setup-mark { background: #1a5fa8; color: #fff; }
        .sh-setup-mark svg { width: 1rem; height: 1rem; }
        .sh-setup-body { flex: 1; min-width: 0; }
        .sh-setup-body strong { display: block; font-size: .9rem; color: #13294b; }
        .sh-setup-body span { display: block; font-size: .8rem; color: #64748b; }
        .sh-setup li.is-done strong { text-decoration: line-through; }
    </style>
</x-filament-widgets::widget>
