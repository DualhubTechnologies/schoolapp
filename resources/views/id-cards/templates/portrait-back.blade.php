{{--
    Portrait back, the same on every card: where to return it, the
    school's rules and the head teacher's signature. $shared from
    IdCardService::schoolData(), $design from design().
--}}
<div class="idc idc-portrait">
    <div class="idc-band p-back-head">
        <div class="idc-kicker">If found, please return to</div>
        <div class="idc-school">{{ Str::limit($shared['school']->name, 44) }}</div>
    </div>
    <div class="idc-wave p-wave-top"><img src="{{ $design['waveTop'] }}" alt=""></div>

    <div class="p-back-body">
        @if ($shared['logoPath'])
            <table><tr><td><div class="idc-crest" style="margin: 0 auto;"><img src="{{ $shared['logoPath'] }}" alt=""></div></td></tr></table>
        @endif
        <div class="p-contacts">@include('id-cards.templates._contacts')</div>
        <div class="p-rule"></div>
        @include('id-cards.templates._notes')
        <div class="p-sign">@include('id-cards.templates._signature')</div>
    </div>

    <div class="idc-wave p-wave-bottom"><img src="{{ $design['waveBottom'] }}" alt=""></div>
    <div class="idc-band p-back-foot"></div>
</div>
