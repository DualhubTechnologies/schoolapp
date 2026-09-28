{{--
    Shared CR80 card styling (85.6mm x 54mm -- the standard ID card size)
    for the live preview and the browser print view. Included inside a
    <style> tag by whichever page needs it. The PDF export uses its own,
    dompdf-safe styles in id-cards/pdf.blade.php.
--}}
    .idc-pair { display: flex; gap: 10mm; }
    .idc-card {
        width: 85.6mm;
        height: 54mm;
        border-radius: 3mm;
        overflow: hidden;
        position: relative;
        background: #fff;
        border: 1px solid #d7deea;
        box-shadow: 0 1px 3px rgba(16, 24, 40, .08);
        font-family: ui-sans-serif, system-ui, sans-serif;
        color: #16233a;
        flex-shrink: 0;
    }
    .idc-band {
        background: linear-gradient(135deg, #1a5fa8, #2563eb);
        color: #fff;
        padding: 2.2mm 3.5mm;
        display: flex;
        align-items: center;
        gap: 2mm;
    }
    .idc-band img { height: 7mm; width: 7mm; object-fit: contain; border-radius: 1mm; background: #fff; }
    .idc-band-text { line-height: 1.15; overflow: hidden; }
    .idc-school-name { font-size: 3.1mm; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .idc-school-sub { font-size: 2mm; opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Front */
    .idc-front-body { display: flex; gap: 3mm; padding: 2.5mm 3.5mm; }
    .idc-photo { width: 17mm; height: 20mm; border-radius: 1.5mm; object-fit: cover; background: #eef2f7; flex-shrink: 0; border: .4mm solid #e4e8f0; }
    .idc-photo-fallback { display: flex; align-items: center; justify-content: center; font-size: 7mm; font-weight: 700; color: #94a3b8; }
    .idc-front-info { min-width: 0; }
    .idc-name { font-size: 3.3mm; font-weight: 700; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .idc-line { font-size: 2.4mm; color: #475569; margin-top: 1mm; }
    .idc-line b { color: #16233a; }
    .idc-front-foot { position: absolute; left: 0; right: 0; bottom: 2mm; display: flex; justify-content: space-between; padding: 0 3.5mm; font-size: 2mm; color: #64748b; }

    /* Back */
    .idc-back-body { padding: 2.5mm 3.5mm; font-size: 2.3mm; line-height: 1.5; color: #334155; }
    .idc-back-title { font-size: 2.1mm; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #1a5fa8; margin-top: 1.8mm; }
    .idc-back-title:first-child { margin-top: 0; }
    .idc-signature { position: absolute; right: 3.5mm; bottom: 2mm; text-align: center; }
    .idc-signature img { height: 6mm; display: block; margin: 0 auto; }
    .idc-signature-label { font-size: 1.8mm; color: #64748b; border-top: .3mm solid #cbd5e1; padding-top: .5mm; margin-top: .5mm; }
    .idc-fine-print { position: absolute; left: 3.5mm; bottom: 2mm; font-size: 1.8mm; color: #94a3b8; max-width: 55mm; }

    .idc-missing {
        background: #fef2f2;
        border: 1px dashed #fca5a5;
        color: #991b1b;
        font-size: 2.4mm;
        padding: 2mm 3mm;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        height: 100%;
    }
