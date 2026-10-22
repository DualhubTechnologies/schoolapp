    {{-- ── FAQ ── --}}
    <section class="section" id="faq">
        <div class="container">
            <div class="section-head" data-reveal>
                <div class="eyebrow">FAQ</div>
                <h2>Frequently asked questions</h2>
            </div>

            @php
                $faqs = [
                    ['What do I need to register?', 'Only your school\'s name, whether it is a primary or secondary school, its district, and your own name, phone number and email. You can add your logo, address, motto and other details afterwards from the School Profile page.'],
                    ['Who becomes the administrator?', 'The person who registers the school becomes its School Admin, with full access. From there you can create logins for other staff and decide which modules each person can open.'],
                    ["What happens when the free term ends?", "Choose a plan and pay by mobile money or bank transfer. If a payment is late, the school keeps working for {$graceDays} more days. After that, access pauses until payment is recorded - your data is never deleted."],
                    ['Can we import our existing student lists?', 'Yes. Download the template, fill it in from your existing Excel register, and import all your learners in one go.'],
                    ['Does it work for both primary and secondary?', 'Yes. Primary schools get nursery and primary classes with PLE-style grading; secondary schools get O-Level (including the competency-based curriculum) and A-Level with subject combinations.'],
                    ['Can parents see their child\'s fees and report cards?', 'Yes, on the Premium and Enterprise plans. Parents sign in on their phone with their phone number and a PIN the school sends them, and see fee balances, receipts and the report cards the school has released. Parent logins never count towards your staff-login limit.'],
                ];
            @endphp
            <div class="faq">
                @foreach ($faqs as [$q, $a])
                    <details>
                        <summary>{{ $q }} <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg></summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
