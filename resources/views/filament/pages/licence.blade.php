@php
    $school = $this->school();
    $st = $this->status();
    $plan = $st['plan'];
    $states = [
        'trial' => ['Free trial', '#1d4ed8', '#eff6ff'],
        'active' => ['Licensed', '#15803d', '#f0fdf4'],
        'grace' => ['Licence ended — renew now', '#b91c1c', '#fef2f2'],
        'expired' => ['Locked: licence ended', '#b91c1c', '#fef2f2'],
        'none' => ['No licence', '#b91c1c', '#fef2f2'],
        'clock' => ['Locked: computer date is wrong', '#b91c1c', '#fef2f2'],
    ];
    [$label, $color, $bg] = $states[$st['state']] ?? [ucfirst($st['state']), '#334155', '#f8fafc'];
    $limit = fn (?int $n): string => $n === null ? 'Unlimited' : number_format($n);
@endphp

<x-filament-panels::page>
    <div class="lc-status" style="border-color: {{ $color }}; background: {{ $bg }}">
        <div>
            <div class="lc-label" style="color: {{ $color }}">{{ $label }}</div>
            @if ($st['state'] === 'clock')
                <p>This computer's date and time are earlier than SchoolHub has already seen, so the licence cannot be checked. Correct the date and time in Windows (Settings → Time &amp; language), then reopen SchoolHub. If the date is right, call SchoolHub on {{ config('contact.phone') }}.</p>
            @elseif ($st['ends_on'])
                <p>
                    @if (in_array($st['state'], ['trial', 'active'], true))
                        Valid until <strong>{{ $st['ends_on']->format('l j F Y') }}</strong> ({{ $st['days_left'] }} {{ Str::plural('day', $st['days_left']) }} left).
                    @elseif ($st['state'] === 'grace')
                        Ended on <strong>{{ $st['ends_on']->format('j F Y') }}</strong>. SchoolHub keeps working until <strong>{{ $st['grace_ends_on']->format('j F Y') }}</strong>, then locks until a new key is entered.
                    @else
                        Ended on <strong>{{ $st['ends_on']->format('j F Y') }}</strong>. Enter a new licence key to carry on. Nothing has been deleted.
                    @endif
                </p>
            @endif
        </div>
        @if ($plan)
            <div class="lc-plan">
                <div><span>Plan</span><b>{{ $plan->name }}</b></div>
                <div><span>Learners</span><b>{{ $limit($plan->max_students) }}</b></div>
                <div><span>Staff logins</span><b>{{ $limit($plan->max_users) }}</b></div>
            </div>
        @endif
    </div>

    <div class="lc-grid">
        <div class="lc-card">
            <h3>1. Pay and send these details</h3>
            <p>Pay by mobile money or bank as agreed with SchoolHub, then send the school's name and code. SchoolHub replies with a licence key for one term or one year.</p>
            <div class="lc-ids">
                <div><span>School name</span><b>{{ $school->name }}</b></div>
                <div><span>School code</span><b class="lc-code">{{ $school->unique_code }}</b></div>
            </div>
            <a class="lc-wa" href="{{ $this->whatsappUrl() }}" target="_blank" rel="noopener">Send on WhatsApp</a>
            <p class="lc-muted">The licence only works for this school's name and code, which is why the school name cannot be changed in School Profile.</p>
        </div>

        <div class="lc-card">
            <h3>2. Enter the licence key</h3>
            @if ($this->canEnterKey())
                <form wire:submit="activate">
                    <textarea wire:model="key" rows="5" class="lc-key" placeholder="SHL1.…" aria-label="Licence key" spellcheck="false"></textarea>
                    @error('key')<div class="lc-error">{{ $message }}</div>@enderror
                    <x-filament::button type="submit" icon="heroicon-m-key" class="lc-submit">Enter licence</x-filament::button>
                </form>
                <p class="lc-muted">No internet is needed: the key is checked on this computer.</p>
            @else
                <p>Ask the school administrator to enter the licence key.</p>
            @endif
        </div>
    </div>

    @if ($this->entered()->isNotEmpty())
        <div class="lc-card">
            <h3>Licences entered</h3>
            @foreach ($this->entered() as $record)
                <div class="lc-row">
                    <span>{{ $record->licence_no }}</span>
                    <span>{{ $record->starts_on->format('j M Y') }} – {{ $record->ends_on->format('j M Y') }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <style>
        .lc-status { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; border: 1px solid; border-left-width: 5px; border-radius: 12px; padding: 1rem 1.2rem; }
        .lc-status p { margin: .35rem 0 0; color: #334155; font-size: .9rem; max-width: 40rem; }
        .lc-label { font-weight: 800; font-size: 1.05rem; }
        .lc-plan { display: flex; gap: 1.25rem; }
        .lc-plan span, .lc-ids span { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
        .lc-plan b { font-size: 1rem; color: #16233a; }
        .lc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
        .lc-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1rem 1.2rem; }
        .lc-card h3 { font-weight: 700; color: #16233a; margin: 0 0 .4rem; }
        .lc-card p { color: #475569; font-size: .875rem; margin: .4rem 0; }
        .lc-ids { display: grid; grid-template-columns: 1fr auto; gap: .75rem; margin: .75rem 0; padding: .75rem; background: #f8fafc; border-radius: 8px; }
        .lc-ids b { color: #16233a; }
        .lc-code { font-family: ui-monospace, Consolas, monospace; font-size: 1.05rem; letter-spacing: .05em; }
        .lc-wa { display: inline-block; background: #16a34a; color: #fff; font-weight: 700; font-size: .85rem; padding: .5rem .9rem; border-radius: 8px; }
        .lc-key { width: 100%; font-family: ui-monospace, Consolas, monospace; font-size: .8rem; border: 1px solid #cbd5e1; border-radius: 8px; padding: .6rem; resize: vertical; }
        .lc-error { color: #b91c1c; font-size: .85rem; margin-top: .35rem; }
        .lc-submit { margin-top: .6rem; }
        .lc-muted { color: #64748b !important; font-size: .78rem !important; }
        .lc-row { display: flex; justify-content: space-between; padding: .45rem 0; border-bottom: 1px solid #f1f5f9; font-size: .875rem; color: #334155; }
        .lc-row:last-child { border-bottom: 0; }
    </style>
</x-filament-panels::page>
