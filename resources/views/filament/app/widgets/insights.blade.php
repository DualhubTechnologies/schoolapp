@php $findings = $this->findings(); @endphp

<x-filament-widgets::widget>
    @if ($findings)
        <section class="shi">
            <header class="shi-head">
                <h3>Worth knowing today</h3>
                <span>From your school's own records</span>
            </header>
            <ul class="shi-list">
                @foreach ($findings as $finding)
                    <li @class(['shi-item', "is-{$finding['tone']}"])>
                        <span class="shi-icon"><x-filament::icon :icon="$finding['icon']" /></span>
                        <div class="shi-text">
                            <strong>{{ $finding['title'] }}</strong>
                            @if ($finding['detail'])<span>{{ $finding['detail'] }}</span>@endif
                        </div>
                        @if ($finding['url'])
                            <a href="{{ $finding['url'] }}" class="shi-go">Open</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <style>
        .shi { background: #fff; border: 1px solid var(--sh-border, #e4e8f0); border-radius: 14px; padding: 1rem 1.25rem .5rem; }
        .shi-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: .35rem; }
        .shi-head h3 { font-family: var(--sh-serif, Georgia, serif); font-size: 1.05rem; font-weight: 700; color: var(--sh-navy-800, #12294a); }
        .shi-head span { font-size: .78rem; color: #64748b; }
        .shi-list { display: grid; }
        .shi-item { display: flex; align-items: flex-start; gap: .8rem; padding: .75rem 0; border-top: 1px solid #f1f5f9; }
        .shi-item:first-child { border-top: 0; }
        .shi-icon { flex: none; display: grid; place-items: center; width: 2.1rem; height: 2.1rem; border-radius: .6rem; }
        .shi-icon svg { width: 1.1rem; height: 1.1rem; }
        .shi-item.is-danger .shi-icon { background: #fef2f2; color: #b91c1c; }
        .shi-item.is-warning .shi-icon { background: #fffbeb; color: #b45309; }
        .shi-item.is-info .shi-icon { background: #eff6ff; color: #1d4ed8; }
        .shi-item.is-success .shi-icon { background: #ecfdf5; color: #047857; }
        .shi-text { flex: 1; min-width: 0; }
        .shi-text strong { display: block; font-size: .9rem; color: #16233a; }
        .shi-text span { display: block; margin-top: .1rem; font-size: .8rem; line-height: 1.45; color: #64748b; }
        .shi-go { flex: none; align-self: center; font-size: .8rem; font-weight: 600; color: var(--sh-navy-700, #1b3a63); padding: .3rem .7rem; border: 1px solid #d6dee9; border-radius: 8px; }
        .shi-go:hover { background: #f4f6fa; }
    </style>
</x-filament-widgets::widget>
