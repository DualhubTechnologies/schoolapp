    {{-- ── Pricing ── --}}
    @if ($plans->isNotEmpty())
    <section class="section section-alt" id="pricing">
        <div class="container" id="pricing-root" data-cycle="term">
            <div class="section-head" data-reveal>
                <div class="eyebrow">Pricing</div>
                <h2>Simple pricing, paid per term</h2>
                <p>Every plan includes every module. Plans differ by the number of learners, staff logins, and parent &amp; student portal access.</p>
            </div>

            @if ($hasYearly)
                <div class="toggle-wrap">
                    <div class="toggle" role="group" aria-label="Billing period">
                        <button type="button" data-set-cycle="term" aria-pressed="true">Per term</button>
                        <button type="button" data-set-cycle="year" aria-pressed="false">Per year</button>
                    </div>
                </div>
            @endif

            <div class="plans">
                @foreach ($plans as $plan)
                    @php
                        $termPrice = $shown($plan->price_per_term);
                        $yearPrice = $plan->price_per_year > 0 ? $shown($plan->price_per_year) : $termPrice * 3;
                        $yearSaving = $termPrice > 0 && $plan->price_per_year > 0
                            ? (int) round((1 - $yearPrice / ($termPrice * 3)) * 100)
                            : 0;
                    @endphp
                    <div @class(['plan', 'featured' => $plan->id === $popular]) data-reveal>
                        @if ($plan->id === $popular)<span class="plan-flag">Most popular</span>@endif
                        <h3>{{ $plan->name }}</h3>
                        <p class="plan-desc">{{ $plan->description }}</p>

                        @if ($plan->contact_sales)
                            <div class="plan-price"><b>Custom pricing</b></div>
                            <p class="plan-sub">Sized to your school — talk to us for a quote.</p>
                            <a href="{{ $demoUrl }}" class="btn btn-block btn-secondary">Contact sales</a>
                        @else
                            <div class="plan-price price-term"><small>UGX</small><b>{{ number_format($termPrice) }}</b><span>per term</span></div>
                            <div class="plan-price price-year"><small>UGX</small><b>{{ number_format($yearPrice) }}</b><span>per year</span></div>
                            <p class="plan-sub price-term">Billed at the start of each term</p>
                            <p class="plan-sub price-year">{{ $yearSaving > 0 ? "Save {$yearSaving}% compared with paying per term" : 'Billed once a year' }}</p>

                            <a href="{{ $startUrl }}" @class(['btn', 'btn-block', 'btn-primary' => $plan->id === $popular, 'btn-secondary' => $plan->id !== $popular])>Start free trial</a>
                        @endif

                        <ul>
                            <li>{!! $check !!} {{ \App\Models\Plan::limitLabel($plan->max_students) }} active learners</li>
                            <li>{!! $check !!} {{ \App\Models\Plan::limitLabel($plan->max_users) }} staff logins</li>
                            <li>{!! $check !!} All modules included</li>
                            @if ($plan->parent_student_login)
                                <li>{!! $check !!} Free parent &amp; student logins</li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="pricing-note">All schools start with a free {{ $trialDays }}-day trial. Pay by mobile money or bank transfer when you're ready.</p>
        </div>
    </section>
    @endif
