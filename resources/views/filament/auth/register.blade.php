@php
    $trialDays = config('subscriptions.trial_days');
    $tick = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>';
@endphp

<x-auth-shell heading="Register your school" subheading="Takes about a minute. Your logo, address and other details can be added later." :wide="true">
    <x-slot:side>
        <span class="shr-pill">{{ $trialDays }}-day free trial</span>
        <h1>Start running your school on SchoolHub today.</h1>
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
                <li><span>1</span><div><strong>Create your account</strong>You're signed in immediately.</div></li>
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
        .shr-pill { align-self: flex-start; margin-bottom: 1.1rem; padding: .3rem .75rem; border-radius: 999px; background: rgba(96, 165, 250, .15); border: 1px solid rgba(147, 197, 253, .3); color: #bfdbfe; font-size: .78rem; font-weight: 600; }
        .shr-ticks { list-style: none; margin: 1.75rem 0 0; padding: 0; display: grid; gap: .7rem; font-size: .92rem; color: #e2e8f0; }
        .shr-ticks li { display: flex; gap: .6rem; align-items: flex-start; }
        .shr-ticks svg { flex: none; width: 1.15rem; height: 1.15rem; margin-top: .15rem; color: #4ade80; }
        .shr-next { margin-top: 2.25rem; padding: 1.25rem 1.25rem .5rem; border-radius: .9rem; background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .1); }
        .shr-next-title { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #93c5fd; }
        .shr-next ol { list-style: none; margin: .9rem 0 0; padding: 0; }
        .shr-next li { position: relative; display: flex; gap: .85rem; padding-bottom: 1rem; font-size: .83rem; color: #94a3b8; line-height: 1.45; }
        .shr-next li:not(:last-child)::after { content: ""; position: absolute; left: .8rem; top: 1.75rem; bottom: .15rem; width: 1px; background: rgba(255, 255, 255, .15); }
        .shr-next li span { flex: none; display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 50%; background: rgba(255, 255, 255, .1); color: #fff; font-size: .75rem; font-weight: 700; }
        .shr-next li strong { display: block; color: #fff; font-size: .9rem; font-weight: 600; }
    </style>
</x-auth-shell>
