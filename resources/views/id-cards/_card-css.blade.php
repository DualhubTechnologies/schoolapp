{{--
    ID card styling, coloured from the school's template ($design from
    IdCardService::design()). Raw CSS, included inside a <style> tag by the
    preview page, the print view and the PDF. It keeps to what dompdf lays
    out reliably -- tables, fixed mm sizes, no flexbox, grid, gradients or
    CSS variables -- so one set of card markup serves all three.
--}}
@php
    $primary = $design['primary'];
    $accent = $design['accent'];
    $onPrimary = $design['onPrimary'];
    $onAccent = $design['onAccent'];
@endphp
    .idc { position: relative; overflow: hidden; box-sizing: border-box; background: #ffffff; border: 0.25mm solid #cdd5e1; border-radius: 3mm; font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; color: #1b2433; line-height: 1.25; text-align: left; }
    .idc * { box-sizing: border-box; }
    .idc table { border-collapse: collapse; width: 100%; }
    .idc td { padding: 0; vertical-align: top; }
    .idc img { display: block; }
    .idc-band { background: {{ $primary }}; color: {{ $onPrimary }}; overflow: hidden; }
    .idc-wave { width: 100%; overflow: hidden; }
    /* Tuck each curve 0.3mm under the band it continues, so no hairline shows between them. */
    .l-wave-top, .p-wave-top { margin-top: -0.3mm; }
    .l-foot, .l-back-foot, .p-foot, .p-back-foot { margin-top: -0.3mm; }
    .idc-wave img { width: 100%; height: 100%; }
    .idc-crest { background: #ffffff; overflow: hidden; }
    .idc-school { font-weight: bold; letter-spacing: 0.2pt; text-transform: uppercase; color: {{ $onPrimary }}; }
    .idc-sub { color: {{ $onPrimary }}; }
    .idc-kicker { font-weight: bold; letter-spacing: 0.45pt; text-transform: uppercase; color: {{ $accent }}; }
    .idc-photo { background: #eef1f6; border: 0.7mm solid {{ $accent }}; overflow: hidden; }
    .idc-photo img { width: 100%; height: 100%; }
    .idc-initial { text-align: center; font-weight: bold; color: #a3adbd; }
    .idc-name { font-weight: bold; text-transform: uppercase; color: {{ $primary }}; overflow: hidden; }
    .idc-fields td { padding-bottom: 0.45mm; vertical-align: top; }
    .idc-fields .f-label { color: #5b6477; white-space: nowrap; }
    .idc-fields .f-colon { color: #5b6477; text-align: center; }
    .idc-fields .f-value { font-weight: bold; color: #1b2433; }
    .idc-foot td { vertical-align: middle; color: {{ $onPrimary }}; white-space: nowrap; }
    .idc-notes td { padding-bottom: 0.5mm; color: #3c4557; }
    .idc-notes .n-dot { color: {{ $accent }}; font-weight: bold; }
    .idc-sign img { height: 6.5mm; margin: 0 auto; }
    .idc-sign-line { border-top: 0.25mm solid #8e98aa; margin-top: 0.6mm; padding-top: 0.5mm; font-size: 4.2pt; letter-spacing: 0.3pt; text-transform: uppercase; color: #5b6477; text-align: center; }

    /* ── Landscape: 85.6 × 54 mm ── */
    .idc-landscape { width: 85.6mm; height: 54mm; }
    .idc-landscape .l-head { height: 11.4mm; padding: 1.5mm 3mm 0; }
    .idc-landscape .l-crest-cell { width: 10.5mm; }
    .idc-landscape .idc-crest { width: 8.6mm; height: 8.6mm; border-radius: 4.3mm; }
    .idc-landscape .idc-crest img { width: 7mm; height: 7mm; margin: 0.8mm; }
    .idc-landscape .idc-school { font-size: 7pt; padding-top: 0.6mm; }
    .idc-landscape .idc-sub { font-size: 4.6pt; margin-top: 0.4mm; }
    .idc-landscape .l-wave-top { height: 3.4mm; }
    .idc-landscape .l-body { height: 31.2mm; padding: 0.6mm 3mm 0; overflow: hidden; }
    .idc-landscape .l-photo-cell { width: 23.5mm; }
    .idc-landscape .idc-photo { width: 21mm; height: 21mm; border-radius: 1.6mm; }
    .idc-landscape .idc-initial { font-size: 18pt; padding-top: 5mm; }
    .idc-landscape .l-role { width: 21mm; margin-top: 1mm; text-align: center; font-size: 4.6pt; font-weight: bold; letter-spacing: 0.6pt; background: {{ $accent }}; color: {{ $onAccent }}; padding: 0.5mm 0; border-radius: 1mm; }
    .idc-landscape .idc-name { font-size: 7.6pt; max-height: 6.6mm; margin-bottom: 0.9mm; }
    .idc-landscape .idc-fields { font-size: 5.3pt; }
    .idc-landscape .idc-fields .f-label { width: 14.5mm; }
    .idc-landscape .idc-fields .f-colon { width: 2mm; }
    .idc-landscape .l-wave-bottom { height: 2.6mm; }
    .idc-landscape .l-foot { height: 5.4mm; padding: 0 3mm; }
    .idc-landscape .l-foot td { height: 5mm; font-size: 4.6pt; }
    .idc-landscape .l-back-head { height: 9mm; padding: 1.5mm 3mm 0; }
    .idc-landscape .l-back-head .idc-kicker { font-size: 4.3pt; }
    .idc-landscape .l-back-head .idc-school { font-size: 6.4pt; margin-top: 0.4mm; }
    .idc-landscape .l-back-body { height: 35mm; padding: 1.4mm 3mm 0; overflow: hidden; }
    .idc-landscape .l-contacts { font-size: 4.8pt; color: #3c4557; line-height: 1.45; margin-bottom: 1.4mm; }
    .idc-landscape .l-contacts b { color: {{ $primary }}; }
    .idc-landscape .idc-notes { font-size: 4.5pt; }
    .idc-landscape .idc-notes .n-dot { width: 2mm; }
    .idc-landscape .l-side { width: 24mm; text-align: center; }
    .idc-landscape .l-side .idc-crest { width: 12mm; height: 12mm; border-radius: 6mm; margin: 0 auto 3mm; border: 0.3mm solid #dfe4ec; }
    .idc-landscape .l-side .idc-crest img { width: 10mm; height: 10mm; margin: 0.7mm; }
    .idc-landscape .l-back-foot { height: 4.4mm; padding: 0 3mm; }
    .idc-landscape .l-back-foot td { height: 4mm; font-size: 4.4pt; text-align: center; }

    /* ── Portrait: 54 × 85.6 mm ── */
    .idc-portrait { width: 54mm; height: 85.6mm; }
    .idc-portrait .p-head { height: 16.6mm; padding: 1.8mm 2.5mm 0; text-align: center; }
    .idc-portrait .idc-crest { width: 8.4mm; height: 8.4mm; border-radius: 4.2mm; margin: 0 auto; }
    .idc-portrait .idc-crest img { width: 6.8mm; height: 6.8mm; margin: 0.8mm; }
    .idc-portrait .idc-school { font-size: 6pt; margin-top: 0.9mm; text-align: center; }
    .idc-portrait .idc-sub { font-size: 4.3pt; margin-top: 0.3mm; text-align: center; }
    .idc-portrait .p-wave-top { height: 3.6mm; }
    .idc-portrait .p-photo-wrap { height: 24.2mm; padding-top: 0.6mm; }
    .idc-portrait .idc-photo { width: 22mm; height: 22mm; margin: 0 auto; border-radius: 2mm; }
    .idc-portrait .idc-initial { font-size: 20pt; padding-top: 5.5mm; }
    .idc-portrait .idc-name { height: 6.4mm; padding: 0.4mm 2.5mm 0; font-size: 7.2pt; text-align: center; }
    .idc-portrait .p-fields { height: 22.6mm; padding: 0 4mm; overflow: hidden; }
    .idc-portrait .idc-fields { font-size: 5.3pt; }
    .idc-portrait .idc-fields .f-label { width: 15mm; }
    .idc-portrait .idc-fields .f-colon { width: 2mm; }
    .idc-portrait .p-wave-bottom { height: 2.8mm; }
    .idc-portrait .p-foot { height: 8.6mm; padding: 0.9mm 2.5mm 0; }
    .idc-portrait .p-foot td { font-size: 4.5pt; text-align: center; padding-bottom: 0.5mm; }
    .idc-portrait .p-back-head { height: 11.4mm; padding: 2mm 2.5mm 0; text-align: center; }
    .idc-portrait .p-back-head .idc-kicker { font-size: 4.3pt; text-align: center; }
    .idc-portrait .p-back-head .idc-school { font-size: 5.8pt; margin-top: 0.6mm; text-align: center; }
    .idc-portrait .p-back-body { height: 63.4mm; padding: 2.4mm 3.5mm 0; overflow: hidden; text-align: center; }
    .idc-portrait .p-back-body .idc-crest { width: 12mm; height: 12mm; border-radius: 6mm; border: 0.3mm solid #dfe4ec; }
    .idc-portrait .p-back-body .idc-crest img { width: 10mm; height: 10mm; margin: 0.7mm; }
    .idc-portrait .p-contacts { font-size: 4.7pt; color: #3c4557; line-height: 1.45; margin: 1.6mm 0 2mm; text-align: center; }
    .idc-portrait .p-contacts b { color: {{ $primary }}; }
    .idc-portrait .p-rule { height: 0.25mm; background: #dfe4ec; margin: 0 2mm 1.8mm; overflow: hidden; }
    .idc-portrait .idc-notes { font-size: 4.5pt; text-align: left; }
    .idc-portrait .idc-notes .n-dot { width: 2mm; }
    .idc-portrait .p-sign { margin: 2.4mm 9mm 0; }
    .idc-portrait .p-back-foot { height: 4.6mm; }
