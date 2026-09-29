{{--
    Portrait front: the learner's own details. $card from
    IdCardService::cardsFor(), $shared from schoolData(), $design from design().
--}}
@php($school = $shared['school'])
<div class="idc idc-portrait">
    <div class="idc-band p-head">
        @if ($shared['logoPath'])
            <div class="idc-crest"><img src="{{ $shared['logoPath'] }}" alt=""></div>
        @endif
        <div class="idc-school">{{ Str::limit($school->name, 44) }}</div>
        @if ($school->motto)
            <div class="idc-sub">{{ Str::limit($school->motto, 46) }}</div>
        @endif
    </div>
    <div class="idc-wave p-wave-top"><img src="{{ $design['waveTop'] }}" alt=""></div>

    <div class="p-photo-wrap">@include('id-cards.templates._photo')</div>
    <div class="p-role"><span>{{ $card['role'] }} ID CARD</span></div>
    <div class="idc-name">{{ Str::limit($card['name'], 38) }}</div>
    <div class="p-fields">@include('id-cards.templates._fields')</div>

    <div class="idc-wave p-wave-bottom"><img src="{{ $design['waveBottom'] }}" alt=""></div>
    <div class="idc-band p-foot">
        <table class="idc-foot">
            <tr><td>Card No. <b>{{ $card['cardNumber'] }}</b></td></tr>
            <tr><td>Issued <b>{{ $card['issuedOn']->format('d/m/Y') }}</b> &nbsp;·&nbsp; Expires <b>{{ $card['expiresOn']->format('d/m/Y') }}</b></td></tr>
        </table>
    </div>
</div>
