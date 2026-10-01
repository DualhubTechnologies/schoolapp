@extends('site.layout')

@section('eyebrow', 'About SchoolHub')
@section('heading', 'Made in Uganda, for Ugandan schools')
@section('lead', 'SchoolHub is school management software built around how primary and secondary schools in Uganda actually work.')

@section('content')
    <section class="section">
        <div class="container">
            <div class="prose">
                <h2>What SchoolHub is</h2>
                <p>SchoolHub brings a school's admissions, fees, exams and report cards, ID cards, staff payroll and finance into one secure system that works on any phone or computer. The calculations that used to take evenings and weekends — grades, aggregates and positions, fee balances, PAYE, NSSF and LST — are done for you.</p>

                <h2>Built for the Ugandan curriculum and payroll</h2>
                <ul class="ticks">
                    <li>{!! $check !!} PLE aggregates and divisions for primary schools</li>
                    <li>{!! $check !!} The competency-based lower-secondary curriculum, and A-Level combinations and points</li>
                    <li>{!! $check !!} Fees per class, term and residency (day or boarding), paid by mobile money or bank</li>
                    <li>{!! $check !!} PAYE, NSSF and Local Service Tax worked out on every payslip</li>
                </ul>

                <h2>Who we are</h2>
                <p>SchoolHub is developed and supported by {{ $contact['company'] }}, based in {{ $contact['location'] }}. We set schools up, train their staff and answer their questions by phone and WhatsApp ({{ $contact['hours'] }}).</p>

                <h2>How we look after your school's data</h2>
                <p>Your school owns its data. Under Uganda's Data Protection and Privacy Act, 2019, the school decides how its learners' and staff records are used, and we look after them on the school's behalf: access by role, every change recorded, and nightly backups. Read the details in <a href="{{ route('filament.app.legal.terms') }}#privacy">How we look after data</a>.</p>
            </div>

            <div class="info-grid" style="margin-top: 3rem">
                <div class="info"><small>Based in</small><b>{{ $contact['location'] }}</b><span>Serving schools across Uganda</span></div>
                <div class="info"><small>Open</small><b>{{ $contact['hours'] }}</b><span>Phone and WhatsApp support</span></div>
                <div class="info"><small>Talk to us</small><b><a href="{{ $telUrl }}">{{ $contact['phone'] }}</a></b><span><a href="{{ $mailUrl }}">{{ $contact['email'] }}</a></span></div>
            </div>
        </div>
    </section>
@endsection
