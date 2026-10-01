@extends('site.layout')

@section('eyebrow', 'Pricing')
@section('heading', 'Simple pricing, paid per term')
@section('lead', 'Pay in Uganda shillings by mobile money or bank transfer. Every plan includes every module, and every school starts with a free '.$trialDays.'-day trial.')

@section('content')
    @include('site.sections.pricing')
    @include('site.sections.faq')
@endsection
