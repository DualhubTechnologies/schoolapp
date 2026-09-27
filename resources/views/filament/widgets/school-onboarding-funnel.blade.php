<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-funnel">
        <x-slot name="heading">Where new schools get stuck</x-slot>
        <x-slot name="description">Schools registered in the last 90 days: how many passed each step on the way to taking their first fees payment.</x-slot>

        @if ($total === 0)
            <p class="sh-fn-empty">No schools have registered in the last 90 days.</p>
        @else
            <div class="sh-fn">
                @foreach ($steps as $step)
                    <div class="sh-fn-row">
                        <span class="sh-fn-label">{{ $step['label'] }}</span>
                        <span class="sh-fn-bar"><span style="width: {{ max(2, $step['percent']) }}%"></span></span>
                        <span class="sh-fn-num"><strong>{{ $step['count'] }}</strong> · {{ $step['percent'] }}%</span>
                    </div>
                @endforeach
            </div>

            @if ($stuck !== [])
                <h3 class="sh-fn-sub">Schools to follow up</h3>
                <div class="sh-fn-table">
                    <table>
                        <thead><tr><th>School</th><th>Stopped before</th><th>Registered</th><th>Administrator</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($stuck as $row)
                                @php($phone = \App\Services\SmsSender::normalisePhone($row['school']->phone))
                                <tr>
                                    <td><strong>{{ $row['school']->name }}</strong><div class="sh-fn-muted">{{ $row['school']->city }}</div></td>
                                    <td><span class="sh-fn-step">{{ $row['step'] }}</span></td>
                                    <td>{{ $row['days'] === 0 ? 'Today' : $row['days'] . ' ' . str('day')->plural($row['days']) . ' ago' }}</td>
                                    <td>{{ $row['admin']?->name ?? '—' }}<div class="sh-fn-muted">{{ $row['school']->phone }}{{ $row['admin'] ? ' · ' . $row['admin']->email : '' }}</div></td>
                                    <td>
                                        @if ($phone)
                                            <a class="sh-fn-wa" target="_blank" rel="noopener"
                                               href="https://wa.me/{{ ltrim($phone, '+') }}?text={{ rawurlencode('Hello ' . ($row['admin']?->name ?? '') . ', this is the SchoolHub team. We noticed ' . $row['school']->name . ' has registered — can we help you get set up?') }}">WhatsApp</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-filament::section>

    <style>
        .sh-fn { display: grid; gap: .55rem; }
        .sh-fn-row { display: grid; grid-template-columns: 9.5rem 1fr 6.5rem; align-items: center; gap: .75rem; font-size: .88rem; }
        .sh-fn-label { color: #334155; font-weight: 600; }
        .sh-fn-bar { height: .75rem; background: #eef2f7; border-radius: 99px; overflow: hidden; }
        .sh-fn-bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #1a5fa8, #3b82f6); }
        .sh-fn-num { text-align: right; color: #64748b; font-size: .82rem; }
        .sh-fn-num strong { color: #0d1f38; }
        .sh-fn-sub { margin: 1.4rem 0 .6rem; font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .sh-fn-table { overflow-x: auto; }
        .sh-fn-table table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .sh-fn-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: .5rem .6rem; background: #f8fafc; }
        .sh-fn-table td { padding: .6rem; border-top: 1px solid #f1f5f9; vertical-align: top; }
        .sh-fn-muted { font-size: .75rem; color: #94a3b8; }
        .sh-fn-step { display: inline-block; padding: .15rem .5rem; border-radius: 99px; background: #fff7ed; color: #c2410c; font-size: .75rem; font-weight: 600; }
        .sh-fn-wa { color: #16a34a; font-weight: 700; text-decoration: none; }
        .sh-fn-empty { color: #64748b; }
        @media (max-width: 640px) { .sh-fn-row { grid-template-columns: 7rem 1fr 4.5rem; } }
    </style>
</x-filament-widgets::widget>
