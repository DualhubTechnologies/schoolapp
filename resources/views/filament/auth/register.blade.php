@php
    $trialDays = config('subscriptions.trial_days');
    $tick = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>';
@endphp

<x-auth-shell eyebrow="New school" heading="Register your school" subheading="Takes about a minute. Your logo, address and other details can be added later." :wide="true">
    <x-slot:side>
        <span class="shr-pill">{{ $trialDays }}-day free trial</span>
        <h1>Start running your school on SchoolHub today.</h1>
        <div class="sha-orn" aria-hidden="true"><span></span><i></i><span></span></div>
        <p class="sha-lead">Every module is included during the trial. No payment details needed.</p>

        <ul class="shr-ticks">
            <li>{!! $tick !!} Students, fees, exams, payroll and finance</li>
            <li>{!! $tick !!} Class levels created for you automatically</li>
            <li>{!! $tick !!} Import your learners from Excel</li>
            <li>{!! $tick !!} Works on phones, tablets and computers</li>
        </ul>

        <div class="shr-next">
            <div class="shr-next-title">What happens next</div>
            <ol>
                <li><span>1</span><div><strong>Create your account</strong>Confirm your email with the 6-digit code we send you.</div></li>
                <li><span>2</span><div><strong>Follow the setup checklist</strong>Term, classes, fees and learners.</div></li>
                <li><span>3</span><div><strong>Invite your team</strong>Bursar, teachers and director of studies.</div></li>
            </ol>
        </div>
    </x-slot:side>

    {{ $this->content }}

    <div class="sha-alt">
        Already registered? <a href="{{ filament()->getLoginUrl() }}">Sign in</a>
    </div>

    <style>
        .shr-pill { align-self: flex-start; margin-bottom: 1.1rem; padding: .3rem .8rem; border-radius: 999px; border: 1px solid rgba(226, 196, 126, .5); color: var(--sha-gold-light); font-size: .7rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; }
        .shr-ticks { list-style: none; margin: 1.75rem 0 0; padding: 0; display: grid; gap: .7rem; font-size: .92rem; color: #dbe2ef; }
        .shr-ticks li { display: flex; gap: .65rem; align-items: flex-start; }
        .shr-ticks svg { flex: none; width: 1.15rem; height: 1.15rem; margin-top: .15rem; color: var(--sha-gold-light); }
        .shr-next { margin-top: 2.25rem; padding: 1.25rem 1.25rem .5rem; border-radius: 6px; background: rgba(255, 255, 255, .04); border: 1px solid rgba(226, 196, 126, .22); }
        .shr-next-title { font-size: .68rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--sha-gold-light); }
        .shr-next ol { list-style: none; margin: .9rem 0 0; padding: 0; }
        .shr-next li { position: relative; display: flex; gap: .85rem; padding-bottom: 1rem; font-size: .83rem; color: #9fadc8; line-height: 1.45; }
        .shr-next li:not(:last-child)::after { content: ""; position: absolute; left: .8rem; top: 1.75rem; bottom: .15rem; width: 1px; background: rgba(226, 196, 126, .25); }
        .shr-next li span { flex: none; display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 50%; border: 1px solid rgba(226, 196, 126, .55); color: var(--sha-gold-light); font-family: var(--sha-serif); font-size: .75rem; font-weight: 700; }
        .shr-next li strong { display: block; color: #fff; font-size: .9rem; font-weight: 600; }
        .shr-terms-link { color: var(--primary-600); font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
        .shr-terms-link:hover { color: var(--sha-ink); }
    </style>
</x-auth-shell>
