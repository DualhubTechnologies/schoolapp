{{--
    Landscape front: the learner's own details. $card from
    IdCardService::cardsFor(), $shared from schoolData(), $design from design().
--}}
@php($school = $shared['school'])
<div class="idc idc-landscape">
    <div class="idc-band l-head">
        <table>
            <tr>
                @if ($shared['logoPath'])
                    <td class="l-crest-cell"><div class="idc-crest"><img src="{{ $shared['logoPath'] }}" alt=""></div></td>
                @endif
                <td>
                    <div class="idc-school">{{ Str::limit($school->name, 46) }}</div>
                    <div class="idc-sub">{{ Str::limit($school->motto ?: $school->address ?: '', 64) }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="idc-wave l-wave-top"><img src="{{ $design['waveTop'] }}" alt=""></div>

    <div class="l-body">
        <table>
            <tr>
                <td class="l-photo-cell">
                    @include('id-cards.templates._photo')
                    <div class="l-role">STUDENT</div>
                </td>
                <td>
                    <div class="idc-name">{{ Str::limit($card['student']->name, 40) }}</div>
                    @include('id-cards.templates._fields')
                </td>
            </tr>
        </table>
    </div>

    <div class="idc-wave l-wave-bottom"><img src="{{ $design['waveBottom'] }}" alt=""></div>
    <div class="idc-band l-foot">
        <table class="idc-foot">
            <tr>
                <td>Card No. <b>{{ $card['cardNumber'] }}</b></td>
                <td style="text-align: center;">Issued <b>{{ $card['issuedOn']->format('d/m/Y') }}</b></td>
                <td style="text-align: right;">Expires <b>{{ $card['expiresOn']->format('d/m/Y') }}</b></td>
            </tr>
        </table>
    </div>
</div>
