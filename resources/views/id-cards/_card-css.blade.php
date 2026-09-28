{{--
    ID card styling for both templates. Raw CSS, included inside a <style>
    tag by the preview page, the print view and the PDF. It keeps to what
    dompdf lays out reliably -- tables, fixed mm sizes, no flexbox, grid or
    gradients -- so one set of card markup serves all three.
--}}
    .idc { position: relative; overflow: hidden; box-sizing: border-box; background: #ffffff; border: 0.25mm solid #cdd5e1; border-radius: 3mm; font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; color: #1b2433; line-height: 1.25; text-align: left; }
    .idc * { box-sizing: border-box; }
    .idc table { border-collapse: collapse; width: 100%; }
    .idc td { padding: 0; vertical-align: top; }
    .idc img { display: block; }
    .idc-label { font-size: 4.4pt; font-weight: bold; letter-spacing: 0.35pt; text-transform: uppercase; color: #7a8497; }
    .idc-value { font-size: 6.4pt; font-weight: bold; color: #1b2433; }
    .idc-rule { height: 0.8mm; background: #c8a24a; overflow: hidden; }
    .idc-crest { width: 9mm; height: 9mm; border-radius: 4.5mm; background: #ffffff; overflow: hidden; }
    .idc-crest img { width: 7.4mm; height: 7.4mm; margin: 0.8mm; }
    .idc-photo { background: #eef1f6; border: 0.25mm solid #cdd5e1; overflow: hidden; }
    .idc-photo img { width: 100%; height: 100%; }
    .idc-initial { text-align: center; font-weight: bold; color: #a3adbd; }
    .idc-sign img { height: 7mm; margin: 0 auto; }
    .idc-sign-line { border-top: 0.25mm solid #8e98aa; margin: 0.6mm 2mm 0; padding-top: 0.6mm; font-size: 4.4pt; letter-spacing: 0.3pt; text-transform: uppercase; color: #7a8497; text-align: center; }

    /* ── Classic: landscape ── */
    .idc-classic { width: 85.6mm; height: 54mm; }
    .idc-classic .c-head { height: 12.6mm; background: #13294b; padding: 1.8mm 3.2mm; overflow: hidden; }
    .idc-classic .c-crest-cell { width: 11mm; }
    .idc-classic .c-school { font-size: 7pt; font-weight: bold; letter-spacing: 0.25pt; text-transform: uppercase; color: #ffffff; padding-top: 0.4mm; }
    .idc-classic .c-motto { font-size: 4.8pt; font-style: italic; color: #e3c77f; margin-top: 0.5mm; }
    .idc-classic .c-tag-cell { width: 15mm; text-align: right; }
    .idc-classic .c-tag { font-size: 4.4pt; font-weight: bold; letter-spacing: 0.45pt; color: #e3c77f; text-transform: uppercase; line-height: 1.35; padding-top: 1mm; }
    .idc-classic .c-body { height: 31.4mm; padding: 3mm 3.2mm 0; overflow: hidden; }
    .idc-classic .c-photo-cell { width: 25mm; }
    .idc-classic .idc-photo { width: 22.5mm; height: 22.5mm; border-radius: 1.5mm; }
    .idc-classic .idc-initial { font-size: 20pt; padding-top: 5.5mm; }
    .idc-classic .c-name { font-size: 8.6pt; font-weight: bold; color: #13294b; max-height: 7.8mm; overflow: hidden; padding-top: 0.6mm; }
    .idc-classic .c-divider { height: 0.25mm; background: #dfe4ec; margin: 1mm 0 1.6mm; overflow: hidden; }
    .idc-classic .c-fields td { padding-bottom: 1.6mm; width: 50%; }
    .idc-classic .c-foot { height: 9mm; background: #f2f4f8; border-top: 0.25mm solid #dfe4ec; padding: 0 3.2mm; overflow: hidden; }
    .idc-classic .c-foot td { vertical-align: middle; height: 8.6mm; }
    .idc-classic .c-foot-title { font-size: 4.8pt; font-weight: bold; letter-spacing: 0.6pt; text-transform: uppercase; color: #13294b; }
    .idc-classic .c-valid { font-size: 5pt; font-weight: bold; color: #13294b; text-align: right; }
    .idc-classic .c-valid span { color: #7a8497; font-weight: normal; }

    .idc-classic .c-back-head { height: 9.4mm; background: #13294b; padding: 1.7mm 3.2mm; overflow: hidden; }
    .idc-classic .c-back-kicker { font-size: 4.4pt; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; color: #e3c77f; }
    .idc-classic .c-back-school { font-size: 6.6pt; font-weight: bold; color: #ffffff; margin-top: 0.5mm; }
    .idc-classic .c-back-body { height: 26.6mm; padding: 2.6mm 3.2mm 0; overflow: hidden; }
    .idc-classic .c-row td { padding-bottom: 1.3mm; }
    .idc-classic .c-row .c-row-label { width: 21mm; padding-top: 0.5mm; }
    .idc-classic .c-row .idc-value { font-size: 6pt; }
    .idc-classic .c-back-foot { height: 17mm; border-top: 0.25mm solid #dfe4ec; padding: 1.8mm 3.2mm 0; overflow: hidden; }
    .idc-classic .c-contact { font-size: 4.8pt; color: #4b5568; line-height: 1.45; }
    .idc-classic .c-property { font-size: 4.2pt; font-style: italic; color: #7a8497; margin-top: 1mm; }
    .idc-classic .c-sign-cell { width: 27mm; text-align: center; vertical-align: bottom; }

    /* ── Portrait: upright ── */
    .idc-portrait { width: 54mm; height: 85.6mm; }
    .idc-portrait .p-head { height: 23mm; background: #13294b; padding: 2.4mm 3mm 0; text-align: center; overflow: hidden; }
    .idc-portrait .idc-crest { margin: 0 auto; width: 10mm; height: 10mm; border-radius: 5mm; }
    .idc-portrait .idc-crest img { width: 8.2mm; height: 8.2mm; margin: 0.9mm; }
    .idc-portrait .p-school { font-size: 6.4pt; font-weight: bold; letter-spacing: 0.25pt; text-transform: uppercase; color: #ffffff; margin-top: 1.4mm; text-align: center; }
    .idc-portrait .p-motto { font-size: 4.5pt; font-style: italic; color: #e3c77f; margin-top: 0.5mm; text-align: center; }
    .idc-portrait .p-photo-wrap { height: 28.4mm; padding-top: 2.6mm; overflow: hidden; }
    .idc-portrait .idc-photo { width: 25mm; height: 25mm; margin: 0 auto; border-radius: 1.5mm; border: 0.6mm solid #13294b; }
    .idc-portrait .idc-initial { font-size: 22pt; padding-top: 6mm; }
    .idc-portrait .p-name { height: 7.6mm; padding: 0.6mm 3mm 0; font-size: 7.8pt; font-weight: bold; color: #13294b; text-align: center; overflow: hidden; }
    .idc-portrait .p-class { height: 6.6mm; text-align: center; overflow: hidden; }
    .idc-portrait .p-class span { display: inline-block; background: #c8a24a; color: #13294b; font-size: 5.2pt; font-weight: bold; letter-spacing: 0.3pt; padding: 0.6mm 2.6mm; border-radius: 2mm; }
    .idc-portrait .p-fields { height: 10.8mm; padding: 0 5mm; overflow: hidden; }
    .idc-portrait .p-fields td { padding-bottom: 0.9mm; }
    .idc-portrait .p-fields .idc-value { font-size: 5.8pt; text-align: right; }
    .idc-portrait .p-foot { height: 7.6mm; background: #13294b; overflow: hidden; }
    .idc-portrait .p-foot td { height: 7.6mm; vertical-align: middle; text-align: center; font-size: 4.8pt; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; color: #ffffff; }
    .idc-portrait .p-foot td span { color: #e3c77f; }

    .idc-portrait .p-back-head { height: 11mm; background: #13294b; padding: 2.2mm 3mm 0; text-align: center; overflow: hidden; }
    .idc-portrait .p-back-title { font-size: 5.8pt; font-weight: bold; letter-spacing: 0.6pt; text-transform: uppercase; color: #ffffff; text-align: center; }
    .idc-portrait .p-back-school { font-size: 4.8pt; color: #e3c77f; margin-top: 0.6mm; text-align: center; }
    .idc-portrait .p-back-body { height: 49.4mm; padding: 3mm 4mm 0; overflow: hidden; }
    .idc-portrait .p-block { margin-bottom: 2.2mm; }
    .idc-portrait .p-block .idc-value { font-size: 5.8pt; margin-top: 0.4mm; }
    .idc-portrait .p-return { background: #f2f4f8; border-radius: 1.5mm; padding: 1.8mm 2.2mm; margin-bottom: 2.4mm; font-size: 4.8pt; color: #4b5568; line-height: 1.45; }
    .idc-portrait .p-return b { color: #13294b; }
    .idc-portrait .p-sign { height: 13mm; text-align: center; overflow: hidden; }
    .idc-portrait .p-property { height: 6.4mm; padding: 0 4mm; font-size: 4.2pt; font-style: italic; color: #7a8497; text-align: center; overflow: hidden; }
    .idc-portrait .p-back-foot { height: 4.4mm; background: #13294b; overflow: hidden; }
