@php
    $user = auth()->user();
    $school = $this->school();
    $rejected = $this->isRejected();

    // What happens between registering and using SchoolHub.
    $steps = [
        ['Registered', $school->created_at?->format('j M Y, g:i a'), true],
        ['Email confirmed', $user->email_verified_at?->format('j M Y, g:i a') ?? 'Not yet', $user->email_verified_at !== null],
        [$rejected ? 'Not approved' : 'SchoolHub checks your school', $rejected ? 'See the reason above' : 'Usually the same day', false],
    ];
    if (! $rejected) {
        $steps[] = ['Free trial starts', config('subscriptions.trial_days', 30).' days, from the day of approval', false];
    }
    $current = $rejected ? 2 : collect($steps)->search(fn ($s) => ! $s[2]);

    $details = [
        'School' => $school->name,
        'Category' => $school->typeLabel(),
        'District / town' => $school->city,
        'Registered by' => $school->contact_person ?: $user->name,
        'Phone' => $school->phone,
        'Email' => $user->email,
        'School code' => $school->unique_code,
    ];
@endphp

<x-filament-panels::page>
    <div @class(['aw', 'aw-rejected' => $rejected])>

        <section class="aw-hero">
            <div class="aw-hero-icon" aria-hidden="true">
                @if ($rejected)
                    <x-filament::icon icon="heroicon-o-x-circle" />
                @else
                    <x-filament::icon icon="heroicon-o-clock" />
                @endif
            </div>
            <div>
                @if ($rejected)
                    <h2>We could not approve {{ $school->name }}</h2>
                    @if ($school->rejection_reason)
                        <p class="aw-reason"><strong>Reason:</strong> {{ $school->rejection_reason }}</p>
                    @endif
                    <p>If you think this is a mistake, or you can give us what was missing, talk to us and we will look again.</p>
                @else
                    <h2>Thank you, {{ $school->name }} is registered</h2>
                    <p>SchoolHub checks every new school before opening it, usually the same day. We will email <strong>{{ $user->email }}</strong> as soon as it is approved, and your {{ config('subscriptions.trial_days', 30) }}-day free trial starts then.</p>
                    <p class="aw-muted">You do not need to do anything else. You can close this page and sign in again later.</p>
                @endif
            </div>
        </section>

        <div class="aw-grid">
            <section class="aw-card">
                <h3>{{ $rejected ? 'Your registration' : 'What happens next' }}</h3>
                <ol class="aw-steps">
                    @foreach ($steps as $i => [$label, $note, $done])
                        <li @class(['is-done' => $done, 'is-current' => $i === $current])>
                            <span class="aw-dot" aria-hidden="true">
                                @if ($done)
                                    <x-filament::icon icon="heroicon-m-check" />
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </span>
                            <span>
                                <strong>{{ $label }}</strong>
                                <small>{{ $note }}</small>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="aw-card">
                <h3>Details you gave us</h3>
                <dl class="aw-details">
                    @foreach ($details as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="aw-muted">Something wrong? Tell us on WhatsApp and we will correct it before approving.</p>
            </section>
        </div>

        <section class="aw-card aw-contact">
            <div>
                <h3>Questions? Talk to us</h3>
                <p class="aw-muted">{{ config('contact.company') }} · {{ config('contact.phone') }} · {{ config('contact.email') }}</p>
            </div>
            <div class="aw-actions">
                <a href="{{ $this->whatsappUrl() }}" target="_blank" rel="noopener" class="aw-btn aw-btn-whatsapp">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" />
                    WhatsApp us
                </a>
                <a href="tel:{{ preg_replace('/\s+/', '', config('contact.phone')) }}" class="aw-btn">
                    <x-filament::icon icon="heroicon-o-phone" />
                    Call
                </a>
                <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                    @csrf
                    <button type="submit" class="aw-btn">
                        <x-filament::icon icon="heroicon-o-arrow-right-on-rectangle" />
                        Sign out
                    </button>
                </form>
            </div>
        </section>
    </div>

    <style>
        .aw { display: grid; gap: 1.25rem; max-width: 64rem; }
        .aw-hero { display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.5rem; border-radius: 1rem; border: 1px solid #fde68a; background: #fffbeb; }
        .aw-hero-icon { flex: none; display: grid; place-items: center; width: 3.25rem; height: 3.25rem; border-radius: 999px; background: #fef3c7; color: #b45309; }
        .aw-hero-icon svg { width: 1.9rem; height: 1.9rem; }
        .aw-hero h2 { margin: 0 0 .4rem; font-size: 1.3rem; font-weight: 700; color: var(--sh-text, #16233a); }
        .aw-hero p { margin: .25rem 0 0; color: var(--sh-text, #16233a); line-height: 1.55; }
        .aw-rejected .aw-hero { border-color: #fecaca; background: #fef2f2; }
        .aw-rejected .aw-hero-icon { background: #fee2e2; color: #dc2626; }
        .aw-reason { padding: .6rem .8rem; border-radius: .6rem; background: #fff; border: 1px solid #fecaca; }
        .aw-muted { color: var(--sh-text-muted, #64748b); font-size: .9rem; }

        .aw-grid { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); }
        .aw-card { padding: 1.25rem 1.4rem; border-radius: 1rem; border: 1px solid var(--sh-border, #e4e8f0); background: var(--sh-surface, #fff); }
        .aw-card h3 { margin: 0 0 1rem; font-size: 1rem; font-weight: 700; color: var(--sh-text, #16233a); }

        .aw-steps { list-style: none; margin: 0; padding: 0; display: grid; gap: .9rem; }
        .aw-steps li { display: flex; gap: .8rem; align-items: flex-start; color: var(--sh-text-muted, #64748b); }
        .aw-steps strong { display: block; color: var(--sh-text, #16233a); font-weight: 600; }
        .aw-steps small { font-size: .82rem; }
        .aw-dot { flex: none; display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 999px; border: 2px solid var(--sh-border, #e4e8f0); font-size: .8rem; font-weight: 700; background: #fff; }
        .aw-dot svg { width: 1rem; height: 1rem; }
        .aw-steps li.is-done .aw-dot { border-color: #16a34a; background: #16a34a; color: #fff; }
        .aw-steps li.is-current .aw-dot { border-color: #d97706; color: #b45309; background: #fffbeb; }
        .aw-rejected .aw-steps li.is-current .aw-dot { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

        .aw-details { margin: 0 0 .75rem; display: grid; gap: .55rem; }
        .aw-details div { display: flex; justify-content: space-between; gap: 1rem; padding-bottom: .5rem; border-bottom: 1px dashed var(--sh-border, #e4e8f0); }
        .aw-details dt { color: var(--sh-text-muted, #64748b); font-size: .88rem; }
        .aw-details dd { margin: 0; font-weight: 600; color: var(--sh-text, #16233a); text-align: right; overflow-wrap: anywhere; }

        .aw-contact { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; }
        .aw-contact h3 { margin-bottom: .25rem; }
        .aw-contact p { margin: 0; }
        .aw-actions { display: flex; flex-wrap: wrap; gap: .6rem; }
        .aw-actions form { margin: 0; }
        .aw-btn { display: inline-flex; align-items: center; gap: .45rem; padding: .6rem 1rem; border-radius: .6rem; border: 1px solid var(--sh-border, #e4e8f0); background: #fff; color: var(--sh-text, #16233a); font-weight: 600; font-size: .9rem; text-decoration: none; cursor: pointer; }
        .aw-btn svg { width: 1.1rem; height: 1.1rem; }
        .aw-btn:hover { background: var(--sh-bg, #f4f6fa); }
        .aw-btn-whatsapp { border-color: #16a34a; background: #16a34a; color: #fff; }
        .aw-btn-whatsapp:hover { background: #15803d; }

        @media (max-width: 640px) {
            .aw-hero { flex-direction: column; padding: 1.1rem; }
            .aw-actions, .aw-actions .aw-btn, .aw-actions form { width: 100%; }
            .aw-actions .aw-btn { justify-content: center; }
        }
    </style>
</x-filament-panels::page>
