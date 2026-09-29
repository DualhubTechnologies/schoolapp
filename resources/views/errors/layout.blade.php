{{--
    Shell for every error page. Plain HTML with inline styles and no
    database calls, so it still shows when the database, the build or the
    session is what failed. Each page sets: code, title, message, and
    optionally hint, reference (server errors) and showHome.
--}}
@php
    $reference = $reference ?? null;
    $whatsApp = config('contact.whatsapp');
    $helpText = 'Hello SchoolHub, I got an error'.($reference ? " (reference {$reference})" : '').' on '.url()->current();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · SchoolHub</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; background: #eef2f7; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #16233a; }
        .card { width: 100%; max-width: 28rem; background: #fff; border-radius: 1.1rem; padding: 2rem 1.6rem 1.6rem; box-shadow: 0 20px 45px -25px rgba(13, 31, 56, .35); text-align: center; }
        .logo { height: 34px; margin: 0 auto 1.4rem; display: block; }
        .code { display: inline-block; font-size: .75rem; font-weight: 700; letter-spacing: .08em; color: #64748b; background: #f1f5f9; border-radius: 999px; padding: .25rem .7rem; margin-bottom: .9rem; }
        h1 { font-size: 1.35rem; margin: 0 0 .6rem; line-height: 1.3; }
        p { margin: 0 0 .8rem; color: #475569; line-height: 1.55; font-size: .95rem; }
        .ref { margin: 1rem 0; padding: .75rem; border: 1px dashed #cbd5e1; border-radius: .7rem; background: #f8fafc; font-size: .85rem; color: #475569; }
        .ref strong { display: block; margin-top: .2rem; font: 700 1.25rem/1.2 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .06em; color: #16233a; }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; margin-top: 1.3rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 2.75rem; padding: .6rem 1.1rem; border-radius: .7rem; font-weight: 600; font-size: .92rem; text-decoration: none; border: 0; cursor: pointer; }
        .btn-primary { background: #1a5fa8; color: #fff; }
        .btn-ghost { background: #f1f5f9; color: #16233a; }
        .help { margin-top: 1.2rem; font-size: .85rem; color: #64748b; }
        .help a { color: #15803d; font-weight: 600; }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ asset('images/schoolhub-logo-light.svg') }}" alt="SchoolHub">
        <div class="code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        @isset($hint)
            <p>{{ $hint }}</p>
        @endisset

        @if ($reference)
            <div class="ref">
                If you contact us, quote this reference:
                <strong>{{ $reference }}</strong>
            </div>
        @endif

        <div class="actions">
            <button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/home') }}')">Go back</button>
            @if ($showHome ?? true)
                <a class="btn btn-primary" href="{{ url('/home') }}">Go to my dashboard</a>
            @endif
        </div>

        @if ($whatsApp)
            <div class="help">
                Still stuck? <a href="https://wa.me/{{ $whatsApp }}?text={{ rawurlencode($helpText) }}" target="_blank" rel="noopener">Chat with SchoolHub on WhatsApp</a>
            </div>
        @endif
    </main>
</body>
</html>
