<x-auth-shell heading="Check your email" subheading="One last step before you sign in.">
    <div class="shv">
        <div class="shv-mail" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
        </div>

        <p class="shv-text">
            We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>. Enter it below to confirm this email address belongs to you.
        </p>

        {{ $this->content }}

        <div class="shv-help">
            <span>Didn't get it? Check Spam or Promotions, or</span>
            {{ $this->resendAction }}
        </div>

        <div class="shv-foot">
            The code works for {{ \App\Support\EmailVerificationCode::EXPIRES_AFTER_MINUTES }} minutes.
            Already confirmed? <a href="{{ filament()->getLoginUrl() }}">Sign in</a>
        </div>
    </div>

    <x-filament-actions::modals />
</x-auth-shell>

@once
<style>
    .shv { display: flex; flex-direction: column; gap: 1.1rem; }
    .shv-mail { width: 3.25rem; height: 3.25rem; border-radius: .9rem; display: grid; place-items: center; background: var(--sha-blue-soft, #eff6ff); color: var(--sha-blue, #2563eb); box-shadow: inset 0 0 0 1px #dbe7fb; }
    .shv-mail svg { width: 1.6rem; height: 1.6rem; }
    .shv-text { margin: 0; font-size: .95rem; line-height: 1.6; color: var(--sha-text, #334155); }
    .shv-text strong { color: var(--sha-ink, #0f172a); word-break: break-all; }
    .shv .fi-one-time-code-input-ctn { gap: .6rem; justify-content: space-between; }
    .shv .fi-one-time-code-input-digit { width: 100%; max-width: 3.4rem; height: 3.6rem; font-size: 1.5rem; font-weight: 700; text-align: center; color: var(--sha-ink, #0f172a); border-radius: .7rem; }
    .shv-help { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-size: .88rem; color: var(--sha-muted, #64748b); }
    .shv-foot { padding-top: 1rem; border-top: 1px solid var(--sha-line, #e2e8f0); font-size: .85rem; color: var(--sha-muted, #64748b); }
    .shv-foot a { color: var(--sha-blue, #2563eb); font-weight: 600; text-decoration: none; }
    .shv-foot a:hover { text-decoration: underline; }
</style>
@endonce
