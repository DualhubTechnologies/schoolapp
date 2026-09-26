{{--
    Live "passwords match" line under a confirmation field
    (App\Support\PasswordStrength::matches). Shown once the person starts
    typing the confirmation; while it is still a prefix of the password it
    says "keep typing" rather than flagging a mismatch.
--}}
<div
    x-data="{
        get password() { return $wire.$get(@js($passwordStatePath)) ?? '' },
        get confirmation() { return $wire.$get(@js($statePath)) ?? '' },
        get state() {
            if (this.confirmation === this.password) return 'match'
            return this.password.startsWith(this.confirmation) ? 'partial' : 'mismatch'
        },
    }"
    x-show="confirmation.length > 0"
    x-cloak
    class="sh-pw-match"
    :class="'sh-pw-match--' + state"
    aria-live="polite"
>
    <svg x-show="state === 'match'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
    <svg x-show="state === 'mismatch'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
    <span x-show="state === 'match'">Passwords match</span>
    <span x-show="state === 'partial'">Matches so far — keep typing</span>
    <span x-show="state === 'mismatch'">Passwords don't match</span>
</div>
