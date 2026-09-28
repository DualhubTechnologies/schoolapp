{{--
    Browser print: one card per printer page, front then back for each
    student in turn -- ready for a duplex card printer, or for someone to
    flip the card by hand between the two. $cards comes from
    IdCardService::cardsFor(); every one here has already passed the
    readiness check in IdCardController.
--}}
@extends('fees.layout')

@section('title', 'ID Cards')

@section('styles')
    @include('id-cards._styles')

    @page { size: 90mm 60mm; margin: 0; }
    .sheet { background: none; box-shadow: none; padding: 0; width: auto; margin: 4mm auto; }
    .idc-card { margin: 0 auto; }
@endsection

@section('content')
    @foreach ($cards as $card)
        <div class="sheet">@include('id-cards._front', ['card' => $card])</div>
        <div class="sheet">@include('id-cards._back', ['card' => $card])</div>
    @endforeach
@endsection
