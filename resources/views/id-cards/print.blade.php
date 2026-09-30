{{--
    Browser print for a card printer: each page is exactly one CR80 card
    in the school's template orientation, front then back for each student
    in turn -- the order a duplex card printer expects. Every card here has
    already passed the readiness check in IdCardController.
--}}
@php($orientation = $design['orientation'])
@extends('fees.layout')

@section('title', 'ID Cards')

@section('styles')
    @include('id-cards._card-css')

    @page { size: {{ $orientation === 'portrait' ? '54mm 85.6mm' : '85.6mm 54mm' }}; margin: 0; }
    .sheet { background: none; box-shadow: none; padding: 0; width: auto; margin: 6mm auto; }
    .idc { margin: 0 auto; box-shadow: 0 2px 10px rgba(13, 31, 56, .12); }
    .hint { max-width: 32rem; margin: 1.25rem auto 0; text-align: center; font-size: .8rem; color: #4b5563; }

    @media print {
        html, body { margin: 0; padding: 0; background: none; }
        .hint { display: none; }
        /*
         * One card per page, exactly the page's size. The on-screen 6mm
         * margin above would push each card onto a second page, and the
         * last card must not add a blank page after it (the toolbar is
         * also a div, so :last-of-type, not :last-child).
         */
        .sheet, .sheet + .sheet { margin: 0; width: {{ $orientation === 'portrait' ? '54mm' : '85.6mm' }}; height: {{ $orientation === 'portrait' ? '85.6mm' : '54mm' }}; overflow: hidden; break-after: page; page-break-after: always; }
        .sheet:last-of-type { break-after: auto; page-break-after: auto; }
        /* The card itself is the page: no outline or rounded corners on PVC. */
        .idc { border: 0; border-radius: 0; box-shadow: none; }
    }
@endsection

@section('content')
    <p class="hint">
        Each page is one {{ $orientation }} card, front then back.
        Choose your card printer and set the paper to CR80 (85.6 × 54 mm) with no margins.
    </p>

    @foreach ($cards as $card)
        <div class="sheet">@include('id-cards.templates.'.$orientation.'-front')</div>
        <div class="sheet">@include('id-cards.templates.'.$orientation.'-back')</div>
    @endforeach
@endsection
