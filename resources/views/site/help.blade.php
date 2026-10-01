@extends('site.layout')

@section('eyebrow', 'Help')
@section('heading', 'Getting started with SchoolHub')
@section('lead', 'How to set up your school, and answers to the questions schools ask most. Still stuck? Call or WhatsApp us and we will help.')
@section('hero-actions')
    <a href="{{ $waUrl }}" class="btn btn-primary btn-lg" target="_blank" rel="noopener">WhatsApp {{ $contact['phone'] }}</a>
    <a href="{{ route('filament.app.site.page', 'contact') }}" class="btn btn-secondary btn-lg">Other ways to reach us</a>
@endsection

@section('content')
    @include('site.sections.how')

    <section class="section section-alt">
        <div class="container">
            <div class="section-head">
                <div class="eyebrow">Setting up</div>
                <h2>Your first week on SchoolHub</h2>
            </div>
            <div class="prose">
                <h2>1. Complete the school profile</h2>
                <p>Add your logo, address, motto and the head teacher's signature. They appear on receipts, report cards and ID cards.</p>
                <h2>2. Set the academic year, term and classes</h2>
                <p>Class levels are created for you when you register. Add streams only if you split a class, for example S.1 East and S.1 West.</p>
                <h2>3. Set up fees</h2>
                <p>Enter what each class pays per term, with separate amounts for day and boarding learners where you need them.</p>
                <h2>4. Import your learners and staff</h2>
                <p>Download the CSV template from the Students page (or the Staff page), fill it in from your existing register in Excel, and import everyone at once. Dates are written day first, e.g. 14-03-2012. SchoolHub checks every row and tells you exactly what to fix before anything is saved.</p>
                <h2>5. Invite your team</h2>
                <p>Create logins for the bursar, teachers and director of studies, and choose which modules each person can open.</p>
            </div>
        </div>
    </section>

    @include('site.sections.faq')
@endsection
