{{--
    SchoolHub privacy policy (public, /privacy-policy).
    Kept true to how the system actually handles data: what it stores,
    who it shares with (SMS, email, SchoolPay), and how long data is kept
    (config/legal.php, config/contact.php). When the wording changes, bump
    config('legal.privacy_version').
--}}
@php
    $contact = config('contact');
    $company = $contact['company'];
    $retention = (int) config('legal.locked_retention_months', 12);
    $deletionDays = (int) config('legal.deletion_days', 30);
    $backupDays = (int) config('legal.backup_days', 14);
    $noticeDays = (int) config('legal.notice_days', 30);
    $updated = \Illuminate\Support\Carbon::parse(config('legal.privacy_version'))->format('j F Y');

    $sections = [
        'who' => 'Who we are',
        'roles' => 'Our role and the school\'s role',
        'collect' => 'What we collect',
        'use' => 'How we use it',
        'children' => 'Information about children',
        'share' => 'Who we share it with',
        'cookies' => 'Cookies',
        'security' => 'How we keep it safe',
        'keep' => 'How long we keep it',
        'rights' => 'Your rights',
        'breach' => 'If something goes wrong',
        'changes' => 'Changes to this policy',
        'contact' => 'Contact us',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - SchoolHub</title>
    <meta name="description" content="How SchoolHub collects, uses, shares and protects personal information about schools, learners, parents and staff, and the rights people have.">
    @include('partials.site-head', ['path' => '/privacy-policy'])
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
        .doc h3 { margin-top: 1.25rem; font-size: .98rem; font-weight: 700; color: var(--ink); }
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
        <h1>Privacy Policy</h1>
        <p>How {{ $company }} collects, uses, shares and protects personal information through SchoolHub and this website, and the rights people have over it.</p>
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
                <li>Each school owns the information it puts into SchoolHub. We hold it on the school's behalf and use it only to run SchoolHub for that school.</li>
                <li>We never sell personal information and never use it for advertising. Our website has no advertising or tracking cookies.</li>
                <li>We share information only with the services that make SchoolHub work, such as the text-message and email services, and only what each one needs.</li>
                <li>You can ask to see, correct or delete personal information. For a learner's or staff member's records, start with the school.</li>
            </ul>
        </div>

        @php $n = 0; @endphp

        <section id="who">
            <h2><span>{{ ++$n }}</span>Who we are</h2>
            <p>SchoolHub is a school management system owned and operated by <b>{{ $company }}</b> ("we", "us"), based in {{ $contact['location'] }}. This policy explains what personal information we handle through SchoolHub and our website, <b>schoolhubug.com</b>, why we handle it, and the choices and rights people have.</p>
            <p>It applies to schools that use SchoolHub, to the staff, learners, parents and guardians whose information schools keep in it, and to visitors to our website. It should be read together with our <a href="{{ route('filament.app.legal.terms') }}">Terms and Conditions</a>.</p>
        </section>

        <section id="roles">
            <h2><span>{{ ++$n }}</span>Our role and the school's role</h2>
            <ul>
                <li>Under Uganda's <b>Data Protection and Privacy Act, 2019</b>, a school that uses SchoolHub decides why and how the information about its learners, parents, guardians and staff is used. The school is the <b>data controller</b>.</li>
                <li>We hold and process that information <b>on the school's behalf and on its instructions</b>, as its <b>data processor</b>. We do not use it for our own purposes.</li>
                <li>For the details schools give us when they register and pay, and for people who contact us or visit our website, we decide how the information is used, and we are responsible for it.</li>
            </ul>
        </section>

        <section id="collect">
            <h2><span>{{ ++$n }}</span>What we collect</h2>
            <h3>When a school registers</h3>
            <ul>
                <li>The school's name, category, location and contact details, and the name, phone number and email address of the person registering, who becomes the School Admin.</li>
                <li>Records of the school's plan and payments to us.</li>
            </ul>
            <h3>What a school keeps in SchoolHub</h3>
            <p>Each school chooses what to enter. Depending on the parts it uses, this can include:</p>
            <ul>
                <li><b>Learners:</b> name, admission number, class and stream, date of birth, gender, photo, LIN, NIN, SchoolPay code, address, medical notes, attendance, marks, report cards, fees, payments and balances.</li>
                <li><b>Parents and guardians:</b> name, relationship to the learner, phone numbers and email address.</li>
                <li><b>Staff:</b> name, contact details, position, photo, date of birth, TIN, NSSF number, salary, allowances and deductions, and bank or mobile money details for paying salaries.</li>
                <li><b>Logins:</b> the name, email address or phone number and password of each person the school gives access to. Passwords are stored in a scrambled form that cannot be read, not even by us.</li>
            </ul>
            <h3>When people use SchoolHub</h3>
            <ul>
                <li>Sign-in records, such as the time, the IP address and the type of browser, so that accounts can be kept secure.</li>
                <li>A record of changes made in each school's account (who added, changed or deleted what, and when), which the school can see.</li>
                <li>Technical error reports, including the page, the user and the browser involved, so that we can find and fix problems.</li>
            </ul>
            <h3>Visitors to our website</h3>
            <ul>
                <li>If you request a demonstration, the details you enter (your name, school, phone number, email address, the number of learners, a preferred date and your message) and the IP address the request came from, to protect the form from abuse.</li>
                <li>Simple visit statistics: which page was viewed, roughly where from (country and city), how the visitor arrived (for example Google or WhatsApp) and the type of device. <b>Your IP address is not stored</b>: it is used only to work out the place, and unique visitors are counted with a code that changes every day and cannot be traced back to you.</li>
            </ul>
        </section>

        <section id="use">
            <h2><span>{{ ++$n }}</span>How we use it</h2>
            <ul>
                <li>To provide SchoolHub to each school: storing its records and showing them to the people the school has authorised.</li>
                <li>To produce what the school asks for, such as receipts, invoices, statements, report cards, payslips and reports.</li>
                <li>To send the text messages and emails the school asks for, such as fee reminders, receipts, parents' sign-in details and messages to parents or staff.</li>
                <li>To send messages about the service itself, such as email verification codes, password resets and reminders before a trial or subscription ends.</li>
                <li>To keep SchoolHub secure, to prevent misuse, and to find and fix problems.</li>
                <li>To answer demo requests and support questions.</li>
                <li>To meet our legal obligations.</li>
            </ul>
            <p>We may use combined, anonymous figures, for example how many schools use SchoolHub, that cannot identify any school or person.</p>
        </section>

        <section id="children">
            <h2><span>{{ ++$n }}</span>Information about children</h2>
            <ul>
                <li>Most learners are children. Their information is entered by their school, which is responsible for having a lawful reason to hold it and for the consent of parents or guardians where the law requires it.</li>
                <li>We use children's information only to provide SchoolHub to their school. We never use it for marketing, never sell it and never share it with advertisers.</li>
                <li>Parents and guardians can see only their own children's fees, receipts and results, through a parent login or a private link the school sends them.</li>
            </ul>
        </section>

        <section id="share">
            <h2><span>{{ ++$n }}</span>Who we share it with</h2>
            <p>We <b>never sell</b> personal information. We share it only where needed to run SchoolHub, and each service receives only what it needs:</p>
            <ul>
                <li><b>Text messages</b> are delivered through <b>Africa's Talking</b>, which receives the phone number and the message.</li>
                <li><b>Emails</b> from the system, such as verification codes and password resets, are sent through <b>Google's email service</b>, which receives the email address and the message.</li>
                <li><b>SchoolPay:</b> if a school connects its SchoolPay account, SchoolPay sends us the details of each payment made to that school's learners, so it can be recorded and receipted.</li>
                <li><b>Our hosting provider</b> runs the secure servers on which SchoolHub and its data are stored.</li>
                <li><b>Authorities</b>, only where the law requires it, such as under a court order.</li>
            </ul>
            <p>Some of these services may process information outside Uganda. Where that happens, we use only established providers and take steps to make sure the information remains protected, as the Data Protection and Privacy Act requires.</p>
        </section>

        <section id="cookies">
            <h2><span>{{ ++$n }}</span>Cookies</h2>
            <p>We use only the cookies SchoolHub needs to work: to keep you signed in, to remember you if you tick "Remember me", and to protect forms from being misused. We do <b>not</b> use advertising cookies or third-party tracking tools.</p>
        </section>

        <section id="security">
            <h2><span>{{ ++$n }}</span>How we keep it safe</h2>
            <ul>
                <li>Every connection to SchoolHub is encrypted (https).</li>
                <li>Each school's records are kept separate, and no school can see another's.</li>
                <li>The School Admin decides what each user can open, so staff see only what their work needs.</li>
                <li>Passwords are stored scrambled, repeated wrong sign-in attempts are blocked, and users can turn on <b>two-step sign-in</b> with an authenticator app.</li>
                <li>Changes are recorded with the name of the person who made them.</li>
                <li>Receipts and report cards carry a QR code that anyone can scan to check they are genuine.</li>
                <li>The data is backed up automatically every night.</li>
                <li>Our team looks at a school's records only when needed to run the service, fix a problem, or answer the school's own request.</li>
            </ul>
            <p>No system can be made completely secure, but we work to protect information from loss, misuse and unauthorised access, and we review our safeguards as SchoolHub grows.</p>
        </section>

        <section id="keep">
            <h2><span>{{ ++$n }}</span>How long we keep it</h2>
            <ul>
                <li>A school's records are kept for as long as the school uses SchoolHub.</li>
                <li>If a school stops paying, its account is locked but nothing is deleted. We keep a locked school's records for at least <b>{{ $retention }} months</b>, so it can come back or ask for a copy.</li>
                <li>On a written request from the School Admin, we delete the school's records within <b>{{ $deletionDays }} days</b>, except anything the law requires us to keep. Copies in our backups are removed as the backups are replaced, within <b>{{ $backupDays }} days</b>.</li>
                <li>Demo requests are kept for as long as needed to follow them up, and are deleted sooner if you ask.</li>
                <li>Website visit statistics contain no information that identifies you.</li>
            </ul>
        </section>

        <section id="rights">
            <h2><span>{{ ++$n }}</span>Your rights</h2>
            <p>Under the Data Protection and Privacy Act, 2019, you have the right to:</p>
            <ul>
                <li>know what personal information is held about you and get a copy of it;</li>
                <li>have information that is wrong or out of date corrected;</li>
                <li>have information deleted where there is no longer a lawful reason to keep it;</li>
                <li>object to your information being used in a particular way.</li>
            </ul>
            <p>Because the school controls the records of its learners, parents, guardians and staff, please <b>contact the school first</b> about those records. We will help the school respond. For anything else, such as a demo request or a School Admin's own details, contact us directly.</p>
            <p>If you are not satisfied with how your information has been handled, you may complain to the <b>Personal Data Protection Office</b> of Uganda.</p>
        </section>

        <section id="breach">
            <h2><span>{{ ++$n }}</span>If something goes wrong</h2>
            <p>If we become aware of a breach that affects personal information, we will act quickly to contain it, tell the schools affected without undue delay so they can meet their own obligations, and notify the authorities as the law requires.</p>
        </section>

        <section id="changes">
            <h2><span>{{ ++$n }}</span>Changes to this policy</h2>
            <p>We may update this policy as SchoolHub changes. The date at the top shows when it last changed. For important changes we will give schools at least <b>{{ $noticeDays }} days'</b> notice by email or in SchoolHub.</p>
        </section>

        <section id="contact">
            <h2><span>{{ ++$n }}</span>Contact us</h2>
            <p>Questions about this policy or about personal information, or a request to see, correct or delete it:</p>
            <div class="contact-card">
                <span><b>{{ $company }}</b></span>
                <span>Address: {{ $company }}, {{ $contact['location'] }}</span>
                <span>Email: <a href="mailto:{{ $contact['support_email'] }}">{{ $contact['support_email'] }}</a></span>
                <span>Phone / WhatsApp: <a href="tel:+256{{ ltrim(preg_replace('/\D/', '', $contact['phone']), '0') }}">{{ $contact['phone'] }}</a></span>
            </div>
        </section>

        <p class="note">Version {{ config('legal.privacy_version') }}.</p>
    </main>
</div>

<footer>
    <div class="foot">
        <span>© {{ date('Y') }} SchoolHub · {{ $company }}</span>
        <span><a href="{{ url('/') }}">Home</a><a href="{{ route('filament.app.legal.terms') }}">Terms</a><a href="{{ filament()->getRegistrationUrl() }}">Register</a><a href="{{ filament()->getLoginUrl() }}">Sign in</a></span>
    </div>
</footer>

</body>
</html>
