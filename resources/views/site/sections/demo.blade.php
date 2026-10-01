    {{-- ── Book a demo / contact ── --}}
    @php
        $demoErrors = $errors->getBag('demo');
        $demoSent = session('demo_sent');
    @endphp
    <section class="section section-alt" id="demo">
        <div class="container demo">
            <div class="demo-intro">
                <div class="eyebrow">Book a demo</div>
                <h2>See SchoolHub working for your school</h2>
                <p>We'll walk you through fees, exams and payroll using a school like yours and answer your questions. The demo is free, takes about 30 minutes, and can be done online or at your school.</p>

                <div class="contact-list">
                    <a href="{{ $waUrl }}" class="contact-item wa" target="_blank" rel="noopener">
                        <span class="icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg></span>
                        <span><small>WhatsApp — fastest reply</small><b>{{ $contact['phone'] }}</b></span>
                        <svg class="go" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </a>
                    <a href="{{ $telUrl }}" class="contact-item">
                        <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg></span>
                        <span><small>Call us</small><b>{{ $contact['phone'] }}</b></span>
                        <svg class="go" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </a>
                    <a href="{{ $mailUrl }}" class="contact-item">
                        <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg></span>
                        <span><small>Email</small><b>{{ $contact['email'] }}</b></span>
                        <svg class="go" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </a>
                </div>

                <ul class="expect">
                    <li>{!! $check !!} A live walkthrough using your own classes and fee structure</li>
                    <li>{!! $check !!} Help importing your learners and setting up your first term</li>
                    <li>{!! $check !!} Honest advice on which plan fits your school</li>
                </ul>
            </div>

            <div class="form-card">
                @if ($demoSent)
                    <div class="success" role="status">
                        <span class="icon"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg></span>
                        <h3>Thank you{{ is_string($demoSent) ? ', ' . $demoSent : '' }}!</h3>
                        <p>We've received your request and will contact you within one working day to arrange your demo. For a faster reply, message us on WhatsApp.</p>
                        <a href="{{ $waUrl }}" class="btn btn-primary" target="_blank" rel="noopener">Message us on WhatsApp</a>
                    </div>
                @else
                    <h3>Request a demo</h3>
                    <p>Tell us a little about your school and we'll get back to you within one working day.</p>

                    @if ($demoErrors->any())
                        <div class="alert alert-error" role="alert">Please check the highlighted fields and try again.</div>
                    @endif

                    <form method="POST" action="{{ route('filament.app.demo-request') }}" novalidate>
                        @csrf
                        <div class="hp" aria-hidden="true">
                            <label for="website">Leave this empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="form-grid">
                            <div @class(['field', 'has-error' => $demoErrors->has('name')])>
                                <label for="d-name">Your name</label>
                                <input id="d-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required maxlength="150" placeholder="e.g. Sarah Nakato">
                                @if ($demoErrors->has('name'))<span class="err">{{ $demoErrors->first('name') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('school_name')])>
                                <label for="d-school">School name</label>
                                <input id="d-school" name="school_name" type="text" value="{{ old('school_name') }}" autocomplete="organization" required maxlength="150" placeholder="Your school's name">
                                @if ($demoErrors->has('school_name'))<span class="err">{{ $demoErrors->first('school_name') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('phone')])>
                                <label for="d-phone">Phone / WhatsApp</label>
                                <input id="d-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required placeholder="07XX XXX XXX">
                                @if ($demoErrors->has('phone'))<span class="err">{{ $demoErrors->first('phone') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('email')])>
                                <label for="d-email">Email <i>(optional)</i></label>
                                <input id="d-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@school.ac.ug">
                                @if ($demoErrors->has('email'))<span class="err">{{ $demoErrors->first('email') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('learners')])>
                                <label for="d-learners">Number of learners <i>(optional)</i></label>
                                <select id="d-learners" name="learners">
                                    <option value="">Select…</option>
                                    @foreach ($learnerRanges as $value => $label)
                                        <option value="{{ $value }}" @selected(old('learners') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($demoErrors->has('learners'))<span class="err">{{ $demoErrors->first('learners') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('preferred_date')])>
                                <label for="d-date">Preferred date <i>(optional)</i></label>
                                <input id="d-date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" min="{{ today()->toDateString() }}">
                                @if ($demoErrors->has('preferred_date'))<span class="err">{{ $demoErrors->first('preferred_date') }}</span>@endif
                            </div>
                            <div @class(['field', 'full', 'has-error' => $demoErrors->has('preferred_contact')])>
                                <span class="field-label" id="d-contact-label">How should we contact you?</span>
                                <div class="radios" role="radiogroup" aria-labelledby="d-contact-label">
                                    @foreach ($contactMethods as $value => $label)
                                        <label><input type="radio" name="preferred_contact" value="{{ $value }}" @checked(old('preferred_contact', 'whatsapp') === $value)><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                                @if ($demoErrors->has('preferred_contact'))<span class="err">{{ $demoErrors->first('preferred_contact') }}</span>@endif
                            </div>
                            <div @class(['field', 'full', 'has-error' => $demoErrors->has('message')])>
                                <label for="d-message">Anything we should know? <i>(optional)</i></label>
                                <textarea id="d-message" name="message" maxlength="2000" placeholder="e.g. We are a day and boarding secondary school and want to move our fees off Excel.">{{ old('message') }}</textarea>
                                @if ($demoErrors->has('message'))<span class="err">{{ $demoErrors->first('message') }}</span>@endif
                            </div>
                        </div>

                        <div class="form-foot">
                            <small>We only use your details to arrange your demo.</small>
                            <button type="submit" class="btn btn-primary btn-lg">Request my demo {!! $arrow !!}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>
