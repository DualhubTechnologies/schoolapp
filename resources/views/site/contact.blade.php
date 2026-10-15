@extends('site.layout')

@section('eyebrow', 'Contact')
@section('heading', 'Talk to the SchoolHub team')
@section('lead', 'Call or WhatsApp us, or book a free demo for your school. We reply within one working day, and usually much sooner.')

@section('content')
    <section class="section">
        <div class="container">
            <div class="info-grid">
                <div class="info">
                    <small>Call or WhatsApp</small>
                    <b><a href="{{ $telUrl }}">{{ $contact['phone'] }}</a></b>
                    <span><a href="{{ $waUrl }}" target="_blank" rel="noopener">Message us on WhatsApp</a></span>
                </div>
                <div class="info">
                    <small>Opening hours</small>
                    <b>{{ $contact['hours'] }}</b>
                    <span>East Africa Time (EAT)</span>
                </div>
                <div class="info">
                    <small>Location</small>
                    <b>{{ $contact['location'] }}</b>
                </div>
                <div class="info">
                    <small>Email</small>
                    <b><a href="{{ $mailUrl }}">{{ $contact['email'] }}</a></b>
                    <span>Already using SchoolHub? <a href="mailto:{{ $contact['support_email'] }}">{{ $contact['support_email'] }}</a></span>
                </div>
            </div>
        </div>
    </section>

    @include('site.sections.demo')
@endsection
