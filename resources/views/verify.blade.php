{{--
    The page a printed document's QR code opens (VerifyDocumentController):
    genuine and current, replaced by a newer copy, or not found. Shows the
    headline result as printed, nothing private.
--}}
@php
    $site = rtrim((string) config('app.url'), '/');
    $state = ! $document ? ($code ? 'missing' : 'form') : ($document->replaced_at ? 'replaced' : 'genuine');
    $summary = $document?->summary ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $code ? $code.' — ' : '' }}Verify a document | SchoolHub</title>
    <link rel="icon" type="image/svg+xml" href="{{ $site }}/images/schoolhub-icon.svg">
    <style>
        :root { --ink: #0d1f38; --muted: #5b6b80; --line: #e2e8f0; --bg: #f4f7fb; --ok: #0f7a4a; --ok-bg: #e8f6ef; --info: #1d4f91; --info-bg: #e8f0fb; --bad: #b42318; --bad-bg: #fdecea; --brand: #1e3a5f; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, Inter, Arial, sans-serif; background: var(--bg); color: var(--ink); line-height: 1.5; }
        header { background: #fff; border-bottom: 1px solid var(--line); }
        header .in, main { max-width: 560px; margin: 0 auto; padding: 0 16px; }
        header .in { display: flex; align-items: center; gap: .5rem; height: 56px; font-weight: 800; color: var(--brand); text-decoration: none; }
        header img { width: 28px; height: 28px; }
        main { padding-top: 24px; padding-bottom: 48px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(13,31,56,.06); }
        .status { display: flex; gap: .75rem; align-items: center; padding: 18px 20px; }
        .status .icon { flex: 0 0 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; font-size: 22px; font-weight: 800; color: #fff; }
        .status h1 { margin: 0; font-size: 1.1rem; }
        .status p { margin: .1rem 0 0; font-size: .9rem; }
        .genuine { background: var(--ok-bg); color: var(--ok); } .genuine .icon { background: var(--ok); }
        .replaced { background: var(--info-bg); color: var(--info); } .replaced .icon { background: var(--info); font-family: Georgia, serif; font-style: italic; }
        .missing { background: var(--bad-bg); color: var(--bad); } .missing .icon { background: var(--bad); }
        dl { margin: 0; padding: 8px 20px 16px; }
        dl div { display: flex; justify-content: space-between; gap: 1rem; padding: 9px 0; border-bottom: 1px solid var(--line); }
        dl div:last-child { border-bottom: 0; }
        dt { color: var(--muted); font-size: .9rem; }
        dd { margin: 0; font-weight: 600; text-align: right; }
        .section { padding: 4px 20px 0; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
        .code { font-family: ui-monospace, Menlo, Consolas, monospace; letter-spacing: .04em; }
        form { display: flex; gap: .5rem; padding: 20px; }
        input { flex: 1; min-width: 0; font: inherit; font-family: ui-monospace, Menlo, Consolas, monospace; text-transform: uppercase; padding: .7rem .8rem; border: 1px solid #cbd5e1; border-radius: 10px; }
        button { font: inherit; font-weight: 700; padding: .7rem 1.1rem; border: 0; border-radius: 10px; background: var(--brand); color: #fff; cursor: pointer; }
        .note { color: var(--muted); font-size: .85rem; margin: 16px 4px 0; }
        h2 { font-size: 1.25rem; margin: 0 0 .25rem; }
    </style>
</head>
<body>
<header><a class="in" href="{{ $site }}/"><img src="{{ $site }}/images/schoolhub-icon.svg" alt="">SchoolHub</a></header>
<main>
    @if ($state === 'form')
        <h2>Verify a document</h2>
        <p class="note" style="margin-top:0">Type the code printed under the QR code on a report card, for example RC-7KQ4-M2XP.</p>
    @endif

    <div class="card">
        @if ($state === 'genuine')
            <div class="status genuine"><span class="icon">✓</span><div><h1>Genuine {{ strtolower($document->typeLabel()) }}</h1><p>Issued by {{ $summary['school'] ?? $document->school?->name }} through SchoolHub.</p></div></div>
        @elseif ($state === 'replaced')
            <div class="status replaced"><span class="icon">i</span><div><h1>Genuine {{ strtolower($document->typeLabel()) }}, since updated</h1><p>{{ $summary['school'] ?? 'The school' }} issued this card, then issued an updated version on {{ $document->replaced_at?->format('j M Y') }}. The details below are what this copy showed; the school can give you the latest one.</p></div></div>
        @elseif ($state === 'missing')
            <div class="status missing"><span class="icon">✕</span><div><h1>No document with this code</h1><p>The code <span class="code">{{ $code }}</span> was not issued by SchoolHub. Check it was typed correctly; if it was, the document may not be genuine.</p></div></div>
        @endif

        @if ($document)
            <dl>
                <div><dt>School</dt><dd>{{ $summary['school'] ?? '' }}</dd></div>
                <div><dt>Learner</dt><dd>{{ $summary['learner'] ?? '' }}</dd></div>
                <div><dt>Admission no.</dt><dd>{{ $summary['admission_no'] ?? '' }}</dd></div>
                <div><dt>Class</dt><dd>{{ $summary['class'] ?? '' }}</dd></div>
                <div><dt>Term</dt><dd>{{ $summary['term'] ?? '' }}{{ ! empty($summary['exam']) ? ' — '.$summary['exam'] : '' }}</dd></div>
            </dl>
            <div class="section">Result</div>
            <dl>
                @foreach ((array) ($summary['result'] ?? []) as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
                <div><dt>Issued</dt><dd>{{ $document->created_at?->format('j M Y') }}</dd></div>
                <div><dt>Code</dt><dd class="code">{{ $document->code }}</dd></div>
            </dl>
        @endif

        @if ($state !== 'genuine')
            <form method="get" action="{{ route('verify.form') }}">
                <input name="code" placeholder="RC-XXXX-XXXX" value="{{ $state === 'missing' ? $code : '' }}" aria-label="Verification code" autocomplete="off" required>
                <button type="submit">Check</button>
            </form>
        @endif
    </div>

    <p class="note">Compare these details with the paper copy. If they differ, the paper copy has been altered. Only the headline result is shown here; full marks stay with the school.</p>
</main>
</body>
</html>
