    {{-- ── Safe and in control: how SchoolHub guards the school's money and records.
         Every point is something the system does (roles, voided receipts kept,
         nightly backups, /verify codes); keep it that way. ── --}}
    @php
        $guards = [
            ['Each person sees only their own work', 'Teachers enter marks, the bursar handles fees, the nurse the sick bay. Nobody opens what their role does not allow.', 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z'],
            ['Receipts cannot be deleted', 'Every receipt shows who recorded it. A mistake is cancelled with a reason and the name of who cancelled it, and stays in the receipt book.', 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z'],
            ['Report cards and receipts can be checked', 'Each one carries a QR code. A parent, school or employer can confirm it is genuine at '.preg_replace('#^https?://(www\.)?#', '', rtrim(route('verify.form'), '/')).'.', 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z'],
            ['Backed up every night', 'The school\'s records and uploaded files are copied every night, and the connection to SchoolHub is encrypted.', 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125'],
        ];
    @endphp
    <section class="section section-alt" id="secure">
        <div class="container">
            <div class="section-head" data-reveal>
                <div class="eyebrow">Private and secure</div>
                <h2>Safe, and the school stays in control</h2>
                <p>The school's money and records are guarded, and the school's information is its own. We never sell it.</p>
            </div>
            <div class="trust-grid">
                @foreach ($guards as [$title, $text, $path])
                    <div class="trust-card" data-reveal>
                        <span class="trust-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg></span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </div>
                @endforeach
            </div>
            <p class="trust-links">
                <a href="{{ route('verify.form') }}">Verify a report card or receipt &rarr;</a>
                <a href="{{ route('filament.app.legal.privacy') }}">Read our privacy policy &rarr;</a>
            </p>
        </div>
    </section>

    <style>
        .trust-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; margin-top: 2.5rem; }
        .trust-card { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); padding: 1.5rem; }
        .trust-ico { display: inline-grid; place-items: center; width: 2.75rem; height: 2.75rem; border-radius: 12px; background: #e8f1fb; color: #0f5f86; }
        .trust-ico svg { width: 1.5rem; height: 1.5rem; }
        .trust-card h3 { margin-top: 1rem; font-size: 1.05rem; font-weight: 700; letter-spacing: -.01em; }
        .trust-card p { margin-top: .5rem; font-size: .92rem; color: var(--slate-600); line-height: 1.55; }
        .trust-links { margin-top: 1.75rem; display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem 2rem; font-weight: 600; }
        .trust-links a { color: var(--primary, #1e40af); text-decoration: none; }
        .trust-links a:hover { text-decoration: underline; }
        @media (max-width: 1024px) { .trust-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 600px) { .trust-grid { grid-template-columns: 1fr; gap: .9rem; margin-top: 1.75rem; } .trust-card { padding: 1.15rem; } }
    </style>
