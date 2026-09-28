{{--
    ID cards on A4 for an outside print shop: fronts first, then backs.
    Each back sheet mirrors its front sheet left-to-right, so printing
    double-sided (flip on the long edge) puts every back behind its own
    front, ready to cut out along the card outlines.
--}}
@php
    $portrait = $template === 'portrait';
    $columns = $portrait ? 3 : 2;
    $pages = $cards->chunk($portrait ? 9 : 8)->values();
    $rowsOf = fn ($page) => $page->values()->chunk($columns)->map(fn ($row) => $row->values()->pad($columns, null));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Cards</title>
    <style>
        @page { margin: 8mm; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; }
        .sheet-title { font-size: 7pt; color: #7a8497; margin: 0 0 2mm 2mm; }
        .grid { border-collapse: collapse; margin: 0 auto; }
        .grid td { padding: 2mm; vertical-align: top; }
        .break { page-break-after: always; }
        @include('id-cards._card-css')
    </style>
</head>
<body>
    @foreach ($pages as $page)
        <div class="break">
            <div class="sheet-title">ID cards — fronts, sheet {{ $loop->iteration }} of {{ $pages->count() }}</div>
            <table class="grid">
                @foreach ($rowsOf($page) as $row)
                    <tr>
                        @foreach ($row as $card)
                            <td>@if ($card)@include('id-cards.templates.'.$template.'-front', ['card' => $card])@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach

    @foreach ($pages as $page)
        <div class="{{ $loop->last ? '' : 'break' }}">
            <div class="sheet-title">ID cards — backs, sheet {{ $loop->iteration }} of {{ $pages->count() }} (print on the reverse of front sheet {{ $loop->iteration }}, flip on the long edge)</div>
            <table class="grid">
                @foreach ($rowsOf($page) as $row)
                    <tr>
                        @foreach ($row->reverse() as $card)
                            <td>@if ($card)@include('id-cards.templates.'.$template.'-back', ['card' => $card])@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
