@extends('site.layout')

@section('eyebrow', 'Features')
@section('heading', 'Everything a Ugandan school runs on, in one system')
@section('lead', 'Students, fees, exams and report cards, ID cards, payroll and finance — with the grades, positions, balances and PAYE worked out for you.')
@section('hero-actions')
    <a href="{{ $startUrl }}" class="btn btn-primary btn-lg">{{ $signedIn ? 'Open your dashboard' : 'Start free trial' }} {!! $arrow !!}</a>
    <a href="{{ $demoUrl }}" class="btn btn-secondary btn-lg">Book a free demo</a>
@endsection

@section('content')
    @include('site.sections.features')
    @include('site.sections.automation')
    @include('site.sections.modules')
    @include('site.sections.how')
    @include('site.sections.windows')
@endsection
