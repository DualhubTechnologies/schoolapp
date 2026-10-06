{{--
    SchoolHub terms and conditions (public, /terms).
    Figures come from config (trial and grace days, notice periods, contact)
    so the page always matches how the system actually behaves.
    When the wording changes, bump config('legal.terms_version').
--}}
@php
    $contact = config('contact');
    $company = $contact['company'];
    $trialDays = (int) config('subscriptions.trial_days', 30);
    $graceDays = (int) config('subscriptions.grace_days', 14);
    $retention = (int) config('legal.locked_retention_months', 12);
    $deletionDays = (int) config('legal.deletion_days', 30);
    $noticeDays = (int) config('legal.notice_days', 30);
    $updated = \Illuminate\Support\Carbon::parse(config('legal.terms_version'))->format('j F Y');

    $sections = [
        'about' => 'About these terms',
        'register' => 'Registering a school',
        'trial' => 'Free trial',
        'fees' => 'Plans, fees and payment',
        'late' => 'Late payment and locking',
        'data' => 'Your school\'s data',
        'privacy' => 'How we look after data',
        'accounts' => 'Accounts and security',
        'use' => 'Acceptable use',
        'ownership' => 'Ownership of SchoolHub',
        'service' => 'Availability and support',
        'leaving' => 'Leaving SchoolHub',
        'liability' => 'Our liability',
        'changes' => 'Changes to these terms',
        'law' => 'Governing law',
        'contact' => 'Contact us',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms and Conditions — SchoolHub</title>
    <meta name="description" content="The terms under which schools use SchoolHub, including the free trial, fees, and how school data is protected.">
    @include('partials.site-head', ['path' => '/terms-and-conditions'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #16233a; --text: #334155; --muted: #64748b; --line: #e4e8f0; --soft: #f4f6fa; --blue: #1a5fa8; --blue-dark: #12294a; --blue-soft: #e3edf9; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; scroll-padding-top: 5.5rem; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--text); background: var(--soft); line-height: 1.7; -webkit-font-smoothing: antialiased; }
        a { color: var(--blue); }
        a:hover { color: var(--blue-dark); }
        :focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; border-radius: 4px; }

        .top { position: sticky; top: 0; z-index: 10; background: rgba(255, 255, 255, .92); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .top-inner { max-width: 72rem; margin: 0 auto; padding: 0 1.5rem; height: 4.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .top img { height: 2.4rem; width: auto; display: block; }
        .top-links { display: flex; align-items: center; gap: 1.25rem; font-size: .9rem; font-weight: 600; }
        .top-links a { color: var(--text); text-decoration: none; }
        .top-links a:hover { color: var(--ink); }
        .btn { display: inline-flex; align-items: center; height: 2.4rem; padding: 0 1rem; border-radius: .5rem; background: var(--blue); color: #fff !important; font-weight: 600; text-decoration: none; }
        .btn:hover { background: var(--blue-dark); }

        .hero { background: linear-gradient(120deg, #0d1f38 0%, #12294a 50%, #1b3a63 100%); color: #cbd5e1; }
        .hero-inner { max-width: 72rem; margin: 0 auto; padding: 3rem 1.5rem 3.25rem; }
        .hero h1 { color: #fff; font-size: clamp(1.9rem, 4vw, 2.6rem); font-weight: 800; letter-spacing: -.025em; line-height: 1.15; }
        .hero p { margin-top: .75rem; max-width: 44rem; color: #a9b6cc; }
        .hero .meta { margin-top: 1.25rem; display: inline-flex; gap: .5rem; padding: .3rem .8rem; border-radius: 999px; background: rgba(36, 114, 196, .22); border: 1px solid rgba(36, 114, 196, .4); font-size: .8rem; font-weight: 600; color: #cfdcef; }

        .layout { max-width: 72rem; margin: 0 auto; padding: 2.5rem 1.5rem 4rem; display: grid; grid-template-columns: 15rem 1fr; gap: 2.5rem; align-items: start; }
        .toc { position: sticky; top: 6rem; padding: 1.25rem; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
        .toc strong { display: block; margin-bottom: .6rem; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
        .toc ol { list-style: none; counter-reset: toc; display: grid; gap: .15rem; }
        .toc li { counter-increment: toc; }
        .toc a { display: flex; gap: .5rem; padding: .3rem .45rem; border-radius: .4rem; font-size: .85rem; color: var(--text); text-decoration: none; line-height: 1.4; }
        .toc a::before { content: counter(toc) "."; min-width: 1.3rem; color: var(--muted); }
        .toc a:hover { background: var(--soft); color: var(--ink); }

        .doc { padding: 2.5rem 2.75rem; border: 1px solid var(--line); border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(13, 31, 56, .04); }
        .summary { margin-bottom: 2.25rem; padding: 1.25rem 1.4rem; border-radius: 12px; background: var(--blue-soft); border: 1px solid #bcd3ee; color: var(--blue-dark); }
        .summary strong { display: block; margin-bottom: .4rem; }
        .summary ul { padding-left: 1.2rem; display: grid; gap: .25rem; font-size: .93rem; }
        .doc section + section { margin-top: 2.25rem; padding-top: 2.25rem; border-top: 1px solid var(--line); }
        .doc h2 { display: flex; align-items: baseline; gap: .6rem; font-size: 1.2rem; font-weight: 700; color: var(--ink); letter-spacing: -.01em; }
        .doc h2 span { flex: none; display: inline-grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: var(--blue); color: #fff; font-size: .8rem; font-weight: 700; transform: translateY(-.1rem); }
        .doc p, .doc ul { margin-top: .8rem; }
        .doc ul { padding-left: 1.3rem; display: grid; gap: .4rem; }
        .doc li::marker { color: var(--blue); }
        .doc b { color: var(--ink); font-weight: 600; }
        .contact-card { margin-top: 1rem; display: grid; gap: .35rem; padding: 1rem 1.2rem; border-radius: 12px; background: var(--soft); border: 1px solid var(--line); }
        .note { margin-top: 2.25rem; font-size: .82rem; color: var(--muted); }

        footer { border-top: 1px solid var(--line); background: #fff; }
        .foot { max-width: 72rem; margin: 0 auto; padding: 1.5rem; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; font-size: .85rem; color: var(--muted); }
        .foot a { color: var(--muted); text-decoration: none; margin-left: 1.25rem; }
        .foot a:hover { color: var(--ink); }

        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .toc { position: static; }
            .doc { padding: 1.75rem 1.25rem; }
            .top-links .hide-sm { display: none; }
        }
        @media print {
            .top, .toc, footer { display: none; }
            .hero { background: none; color: #000; }
            .hero h1 { color: #000; }
            .layout { display: block; padding: 0; }
            .doc { border: 0; box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

<header class="top">
    <div class="top-inner">
        <a href="{{ url('/') }}" aria-label="SchoolHub home"><img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="SchoolHub" width="185" height="44"></a>
        <nav class="top-links">
            <a href="{{ url('/') }}" class="hide-sm">Home</a>
            <a href="{{ filament()->getLoginUrl() }}" class="hide-sm">Sign in</a>
            <a href="{{ filament()->getRegistrationUrl() }}" class="btn">Register your school</a>
        </nav>
    </div>
</header>

<div class="hero">
    <div class="hero-inner">
        <h1>Terms and Conditions</h1>
        <p>The agreement between your school and {{ $company }}, the provider of SchoolHub. Please read it before registering. By registering a school, you accept these terms on the school's behalf.</p>
        <span class="meta">Last updated {{ $updated }}</span>
    </div>
</div>

<div class="layout">
    <nav class="toc" aria-label="Contents">
        <strong>Contents</strong>
        <ol>
            @foreach ($sections as $id => $title)
                <li><a href="#{{ $id }}">{{ $title }}</a></li>
            @endforeach
        </ol>
    </nav>

    <main class="doc">
        <div class="summary">
            <strong>The short version</strong>
            <ul>
                <li>Your school owns its data. We only use it to run SchoolHub for you, and we never sell it.</li>
                <li>Every new school gets a free {{ $trialDays }}-day trial with every module. No payment details are needed.</li>
                <li>After that you pay per term or per year, in UGX. If payment is late, the school keeps working for {{ $graceDays }} days, then is locked until payment — nothing is deleted.</li>
                <li>You can leave at any time and ask us for a copy of your data, or to delete it.</li>
            </ul>
        </div>

        @php $n = 0; @endphp

        <section id="about">
            <h2><span>{{ ++$n }}</span>About these terms</h2>
            <p>SchoolHub is owned and operated by <b>{{ $company }}</b> ("we", "us"), headquartered in {{ $contact['location'] }}. These terms apply to every school that uses SchoolHub ("the school", "you") and to everyone the school gives a login to.</p>
            <p>By registering a school, or by using SchoolHub, you agree to these terms. If you do not agree, please do not register or use the service.</p>
        </section>

        <section id="register">
            <h2><span>{{ ++$n }}</span>Registering a school</h2>
            <ul>
                <li>You may only register a school you are authorised to act for — for example as its head teacher, director, proprietor or administrator.</li>
                <li>The person who registers becomes the school's <b>School Admin</b> and is responsible for the school's account, including the other staff logins they create.</li>
                <li>The details you give (school name, category, location, your name, phone and email) must be accurate and kept up to date in School Profile.</li>
                <li>We may refuse or close a registration that is false, duplicated, or made without the school's authority.</li>
            </ul>
        </section>

        <section id="trial">
            <h2><span>{{ ++$n }}</span>Free trial</h2>
            <ul>
                <li>Each new school gets a free trial of <b>{{ $trialDays }} days</b> with every module included.</li>
                <li>No payment or card details are needed to start the trial.</li>
                <li>We remind the school's administrators before the trial ends. To keep using SchoolHub after the trial, choose a plan and pay for it.</li>
                <li>One free trial is available per school.</li>
            </ul>
        </section>

        <section id="fees">
            <h2><span>{{ ++$n }}</span>Plans, fees and payment</h2>
            <ul>
                <li>Plans are priced <b>per term or per year, in Uganda shillings</b>. Current prices are shown on our website. Every plan includes every feature; plans differ only by the number of active learners and staff logins.</li>
                <li>Payment is by mobile money or bank transfer, to the details we give you. A subscription period starts once payment is recorded.</li>
                <li>If a school needs more learners or logins than its plan allows, it must move to a larger plan.</li>
                <li>We may change prices with at least <b>{{ $noticeDays }} days'</b> notice. A change does not affect a period you have already paid for.</li>
                <li>Fees already paid are not refundable, except where we are unable to provide the service through our own fault, or where the law requires a refund.</li>
            </ul>
        </section>

        <section id="late">
            <h2><span>{{ ++$n }}</span>Late payment and locking</h2>
            <ul>
                <li>If a subscription is not renewed by its end date, the school keeps working normally for a grace period of <b>{{ $graceDays }} days</b>, with a reminder on screen.</li>
                <li>After the grace period the school's account is <b>locked</b>: staff can sign in only to see how to renew. Locking never deletes any data.</li>
                <li>The account unlocks as soon as payment is recorded.</li>
                <li>We keep a locked school's data for at least <b>{{ $retention }} months</b>. After that we may contact the school and, if it does not renew or ask for its data, delete it.</li>
            </ul>
        </section>

        <section id="data">
            <h2><span>{{ ++$n }}</span>Your school's data</h2>
            <ul>
                <li><b>The school owns all the data it puts into SchoolHub</b> — learners, parents and guardians, staff, fees, marks, payroll and finance records.</li>
                <li>For the purposes of Uganda's <b>Data Protection and Privacy Act, 2019</b>, the school decides why and how this personal data is used (it is the data controller), and we handle it on the school's behalf.</li>
                <li>The school is responsible for having a lawful reason to hold this data, for telling parents, guardians and staff how it is used, and for getting consent where the law requires it — particularly for information about children.</li>
                <li>The school is responsible for the accuracy of what it enters, such as marks, fee balances and salaries.</li>
            </ul>
        </section>

        <section id="privacy">
            <h2><span>{{ ++$n }}</span>How we look after data</h2>
            <ul>
                <li>We use school data <b>only to provide SchoolHub</b> to that school: to store it, show it to the school's authorised users, and send the messages the school asks for.</li>
                <li>We <b>never sell</b> school data, and we do not use it for advertising.</li>
                <li>Our staff look at a school's data only when needed to run the service, fix a problem, or answer the school's own support request.</li>
                <li>We protect data with access controls, encrypted connections and regular backups, and keep a record of changes made in each school's account.</li>
                <li>To send emails and text messages, we use trusted service providers who receive only what is needed to deliver each message.</li>
                <li>If we become aware of a data breach affecting a school, we will tell the school without undue delay so it can meet its own obligations.</li>
                <li>We may keep anonymous, combined statistics (for example, the number of schools using SchoolHub) that cannot identify any school or person.</li>
            </ul>
        </section>

        <section id="accounts">
            <h2><span>{{ ++$n }}</span>Accounts and security</h2>
            <ul>
                <li>Each person should have their own login. Keep passwords secret and do not share accounts.</li>
                <li>The School Admin decides which modules each user can open, and should remove logins for staff who leave.</li>
                <li>The school is responsible for what is done through its accounts. Tell us straight away if you think an account has been misused.</li>
            </ul>
        </section>

        <section id="use">
            <h2><span>{{ ++$n }}</span>Acceptable use</h2>
            <p>You must not use SchoolHub to:</p>
            <ul>
                <li>break the law, or store information you have no right to hold;</li>
                <li>send messages to parents or others that are unlawful, abusive or unrelated to the school;</li>
                <li>try to reach another school's data, test or break our security, or disrupt the service;</li>
                <li>copy, resell or give others access to SchoolHub outside your school.</li>
            </ul>
            <p>We may suspend an account that breaks these rules, and will tell the school why unless the law prevents us.</p>
        </section>

        <section id="ownership">
            <h2><span>{{ ++$n }}</span>Ownership of SchoolHub</h2>
            <p>SchoolHub, including its software, design, text, logos and other content, is the intellectual property of <b>{{ $company }}</b>. Your subscription lets your school use SchoolHub; it does not transfer any ownership of it.</p>
            <p>You are not allowed to copy, modify, distribute, resell or create derivative works from SchoolHub without our written permission. Your school's own records stay yours (see <a href="#data">Your school's data</a>).</p>
        </section>

        <section id="service">
            <h2><span>{{ ++$n }}</span>Availability and support</h2>
            <ul>
                <li>We work to keep SchoolHub available and reliable, but we cannot promise it will never be interrupted — for example during maintenance, or because of internet or power problems outside our control.</li>
                <li>We try to carry out planned maintenance outside school hours.</li>
                <li>We may improve or change features over time. We will not remove a core feature a school depends on without reasonable notice.</li>
                <li>Support is available by WhatsApp, phone and email (see <a href="#contact">Contact us</a>).</li>
            </ul>
        </section>

        <section id="leaving">
            <h2><span>{{ ++$n }}</span>Leaving SchoolHub</h2>
            <ul>
                <li>You may stop using SchoolHub at any time by not renewing.</li>
                <li>While your data is still held, you can ask us for a copy of it in a common format such as Excel or CSV.</li>
                <li>On a written request from the School Admin, we will delete the school's data within <b>{{ $deletionDays }} days</b>, except anything the law requires us to keep.</li>
                <li>We may close a school's account if it breaks these terms, after giving notice where we reasonably can.</li>
            </ul>
        </section>

        <section id="liability">
            <h2><span>{{ ++$n }}</span>Our liability</h2>
            <ul>
                <li>SchoolHub is a tool to help run a school. Decisions based on it — such as promotions, fee demands or payroll — remain the school's responsibility.</li>
                <li>So far as the law allows, we are not responsible for indirect losses, such as lost income, and our total responsibility to a school in any year is limited to the fees that school paid us in that year.</li>
                <li>Nothing in these terms limits any responsibility that cannot be limited under the laws of Uganda.</li>
            </ul>
        </section>

        <section id="changes">
            <h2><span>{{ ++$n }}</span>Changes to these terms</h2>
            <p>We may update these terms from time to time. For important changes we will give at least <b>{{ $noticeDays }} days'</b> notice by email or in SchoolHub. The date at the top of this page shows when they last changed. Continuing to use SchoolHub after a change takes effect means accepting the new terms.</p>
        </section>

        <section id="law">
            <h2><span>{{ ++$n }}</span>Governing law</h2>
            <p>These terms are governed by the laws of the <b>Republic of Uganda</b>. We will always try to settle any disagreement by talking first; if that fails, the courts of Uganda will decide it.</p>
        </section>

        <section id="contact">
            <h2><span>{{ ++$n }}</span>Contact us</h2>
            <p>Questions about these terms, your data, or a deletion request:</p>
            <div class="contact-card">
                <span><b>{{ $company }}</b></span>
                <span>Address: {{ $company }}, {{ $contact['location'] }}</span>
                <span>Phone / WhatsApp: <a href="tel:+256{{ ltrim(preg_replace('/\D/', '', $contact['phone']), '0') }}">{{ $contact['phone'] }}</a></span>
                <span>Email: <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></span>
            </div>
        </section>

        <p class="note">Version {{ config('legal.terms_version') }}.</p>
    </main>
</div>

<footer>
    <div class="foot">
        <span>© {{ date('Y') }} SchoolHub · {{ $company }}</span>
        <span><a href="{{ url('/') }}">Home</a><a href="{{ filament()->getRegistrationUrl() }}">Register</a><a href="{{ filament()->getLoginUrl() }}">Sign in</a></span>
    </div>
</footer>

</body>
</html>
