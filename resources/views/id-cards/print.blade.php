{{--
    Browser print for a card printer: each page is exactly one CR80 card
    (landscape or portrait, as the school's design), front then back for
    each student in turn -- the order a duplex card printer expects.
    $cards comes from IdCardService::cardsFor(); every one has already
    passed the readiness check in IdCardController.
--}}
@extends('fees.layout')

@section('title', 'ID Cards')

@section('styles')
    @include('id-cards._card-css')

    @page { size: {{ $template === 'portrait' ? '54mm 85.6mm' : '85.6mm 54mm' }}; margin: 0; }
    .sheet { background: none; box-shadow: none; padding: 0; width: auto; margin: 6mm auto; }
    .idc { margin: 0 auto; box-shadow: 0 2px 10px rgba(13, 31, 56, .12); }
    .hint { max-width: 32rem; margin: 1.25rem auto 0; text-align: center; font-size: .8rem; color: #4b5563; }

    @media print {
        .hint { display: none; }
        /* The card itself is the page: no outline or rounded corners on PVC. */
        .idc { border: 0; border-radius: 0; box-shadow: none; }
    }
@endsection

@section('content')
    <p class="hint">
        Each page is one {{ $template === 'portrait' ? 'portrait' : 'landscape' }} card, front then back.
        Choose your card printer and set the paper to CR80 (85.6 × 54 mm) with no margins.
    </p>

    @foreach ($cards as $card)
        <div class="sheet">@include('id-cards.templates.'.$template.'-front', ['card' => $card])</div>
        <div class="sheet">@include('id-cards.templates.'.$template.'-back', ['card' => $card])</div>
    @endforeach
@endsection
