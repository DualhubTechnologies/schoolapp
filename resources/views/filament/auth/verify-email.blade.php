<x-auth-shell eyebrow="Email confirmation" heading="Check your email" subheading="One last step before you sign in.">
    <div class="shv">
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

    <style>
        .shv { display: flex; flex-direction: column; gap: 1.1rem; }
        .shv-text { margin: 0; font-size: .95rem; line-height: 1.6; color: var(--sha-text, #334155); text-align: center; }
        .shv-text strong { color: var(--sha-ink, #0f172a); word-break: break-all; }
        .shv .fi-one-time-code-input-ctn { gap: .6rem; justify-content: space-between; }
        .shv .fi-one-time-code-input-digit { width: 100%; max-width: 3.4rem; height: 3.6rem; font-family: var(--sha-serif); font-size: 1.5rem; font-weight: 700; text-align: center; color: var(--sha-ink, #0f172a); border-radius: 6px; }
        .shv-help { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .35rem; font-size: .88rem; color: var(--sha-muted, #64748b); }
        .shv-foot { padding-top: 1rem; border-top: 1px solid var(--sha-soft, #efeae0); font-size: .85rem; color: var(--sha-muted, #64748b); text-align: center; }
        .shv-foot a { color: var(--primary-600); font-weight: 600; text-decoration: none; }
        .shv-foot a:hover { text-decoration: underline; }
    </style>
</x-auth-shell>
