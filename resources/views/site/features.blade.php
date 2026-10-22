@extends('site.layout')

@section('eyebrow', 'Features')
@section('heading', 'Everything a Ugandan school runs on, in one system')
@section('lead', 'Students, fees, exams and report cards, ID cards, payroll and finance — with the grades, balances and PAYE worked out for you, and report cards generated in seconds.')
@section('hero-actions')
    <a href="{{ $startUrl }}" class="btn btn-primary btn-lg">{{ $signedIn ? 'Open your dashboard' : 'Start your free term' }} {!! $arrow !!}</a>
    <a href="{{ $demoUrl }}" class="btn btn-secondary btn-lg">Book a free demo</a>
@endsection

@section('content')
    @include('site.sections.features', ['allModules' => true])
    @include('site.sections.automation')
    @include('site.sections.modules')
    @include('site.sections.designer')
    @include('site.sections.how')
    @include('site.sections.windows')
@endsection
