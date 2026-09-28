{{--
    Landscape back, the same on every card: where to return it, the
    school's rules and the head teacher's signature. $shared from
    IdCardService::schoolData(), $design from design().
--}}
<div class="idc idc-landscape">
    <div class="idc-band l-back-head">
        <div class="idc-kicker">If found, please return to</div>
        <div class="idc-school">{{ Str::limit($shared['school']->name, 52) }}</div>
    </div>
    <div class="idc-wave l-wave-top"><img src="{{ $design['waveTop'] }}" alt=""></div>

    <div class="l-back-body">
        <table>
            <tr>
                <td>
                    <div class="l-contacts">@include('id-cards.templates._contacts')</div>
                    @include('id-cards.templates._notes')
                </td>
                <td class="l-side">
                    @if ($shared['logoPath'])
                        <div class="idc-crest"><img src="{{ $shared['logoPath'] }}" alt=""></div>
                    @endif
                    @include('id-cards.templates._signature')
                </td>
            </tr>
        </table>
    </div>

    <div class="idc-wave l-wave-bottom"><img src="{{ $design['waveBottom'] }}" alt=""></div>
    <div class="idc-band l-back-foot">
        <table class="idc-foot"><tr><td>Student Identity Card</td></tr></table>
    </div>
</div>
