<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Cards</title>
    <style>
        @page { margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #16233a; }

        .sheet-title { font-size: 9pt; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4mm; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; padding: 2.5mm; vertical-align: top; }
        .grid-page { page-break-after: always; }
        .grid-page:last-child { page-break-after: auto; }

        .p-card { width: 85.6mm; height: 54mm; border: 0.3mm solid #d7deea; border-collapse: collapse; }
        .p-card td { padding: 0; }
        .p-band-inner { width: 100%; border-collapse: collapse; }
        .p-band-inner td { padding: 0; }

        .p-band { background: #2563eb; color: #fff; padding: 2mm 3mm; }
        .p-logo-cell { width: 8mm; }
        .p-logo { height: 7mm; width: 7mm; }
        .p-school-name { font-size: 8pt; font-weight: bold; }
        .p-school-sub { font-size: 6pt; color: #dbe6fb; }

        .p-photo-cell { width: 19mm; padding: 2mm; }
        .p-photo { width: 17mm; height: 20mm; }
        .p-photo-fallback { display: table-cell; text-align: center; vertical-align: middle; background: #eef2f7; color: #94a3b8; font-size: 14pt; font-weight: bold; }
        .p-info-cell { padding: 2mm 3mm 2mm 0; }
        .p-name { font-size: 8pt; font-weight: bold; }
        .p-line { font-size: 6pt; color: #475569; margin-top: 0.8mm; }

        .p-foot { padding: 1mm 3mm; font-size: 6pt; color: #64748b; border-top: 0.2mm solid #eef2f7; }
        .p-foot-right { text-align: right; }

        .p-back-body { padding: 2mm 3mm; font-size: 6pt; line-height: 1.5; color: #334155; }
        .p-back-title { font-size: 5.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #1a5fa8; margin-top: 1.5mm; }
        .p-sign-row { padding: 1mm 3mm; border-top: 0.2mm solid #eef2f7; }
        .p-fine-print { font-size: 5pt; color: #94a3b8; }
        .p-sign-cell { width: 18mm; text-align: center; }
        .p-sign { height: 5mm; }
        .p-sign-label { font-size: 5pt; color: #64748b; border-top: 0.2mm solid #cbd5e1; }
    </style>
</head>
<body>

    {{-- Fronts, then backs, in the same 2x4 order on every sheet -- print
    fronts, flip the stack over as a whole, print backs, then cut. --}}
    @foreach ($cards->chunk(8) as $page)
        <div class="grid-page">
            <div class="sheet-title">ID Cards — Front</div>
            <table class="grid">
                @foreach ($page->chunk(2) as $row)
                    <tr>
                        @foreach ($row as $card)
                            <td>@include('id-cards._front_pdf', ['card' => $card])</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach

    @foreach ($cards->chunk(8) as $page)
        <div class="grid-page">
            <div class="sheet-title">ID Cards — Back</div>
            <table class="grid">
                @foreach ($page->chunk(2) as $row)
                    <tr>
                        @foreach ($row as $card)
                            <td>@include('id-cards._back_pdf', ['card' => $card])</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach

</body>
</html>
