{{--
    Shell for printable fee documents (receipts, reminder letters).
    Plain HTML + inline CSS so it prints the same on any machine.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #111827; background: #eef1f6; font-size: 13px; line-height: 1.45; }
        .toolbar { position: sticky; top: 0; display: flex; justify-content: center; gap: .5rem; padding: .75rem; background: #0d1f38; }
        .toolbar button, .toolbar a { font: inherit; font-weight: 600; font-size: .85rem; padding: .5rem 1rem; border-radius: 8px; border: 0; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #2472c4; color: #fff; }
        .toolbar .ghost { background: rgba(255,255,255,.1); color: #fff; }
        .sheet { background: #fff; margin: 1.5rem auto; padding: 1.75rem 2rem; box-shadow: 0 2px 10px rgba(13,31,56,.08); position: relative; overflow: hidden; }
        .sheet + .sheet { margin-top: 1.5rem; }

        .letterhead { display: flex; align-items: center; gap: 1rem; padding-bottom: .9rem; border-bottom: 3px double #1e3a5f; }
        .letterhead img { width: 3.75rem; height: 3.75rem; object-fit: contain; }
        .letterhead .who { flex: 1; }
        .letterhead h1 { font-size: 1.2rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; color: #1e3a5f; }
        .letterhead p { font-size: .78rem; color: #4b5563; }

        .muted { color: #6b7280; }
        .label { font-size: .68rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; }
        .strong { font-weight: 700; }
        .num { font-variant-numeric: tabular-nums; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @yield('styles')
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="primary" onclick="window.print()">Print</button>
        <button class="ghost" onclick="window.close()">Close</button>
    </div>

    @yield('content')

    <script>
        // Open the print dialog straight away when asked (?print=1).
        if (new URLSearchParams(location.search).get('print') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        }
    </script>
</body>
</html>
