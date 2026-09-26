{{--
    Live password checklist under a password field (App\Support\PasswordStrength::meter).
    Reads the field's value from Livewire as the person types; nothing is sent
    to the server until the form is saved, where the same rules are enforced.
--}}
<div
    x-data="{
        checks: @js($requirements),
        get value() { return $wire.$get(@js($statePath)) ?? '' },
        passes(check) { return new RegExp(check.pattern, 'u').test(this.value) },
        get passed() { return this.checks.filter((check) => this.passes(check)).length },
        get level() {
            if (this.passed === this.checks.length) return 'strong'
            return this.passed / this.checks.length >= 0.6 ? 'fair' : 'weak'
        },
    }"
    x-show="value.length > 0"
    x-cloak
    class="sh-pw"
    :class="'sh-pw--' + level"
>
    <div class="sh-pw-bar" aria-hidden="true">
        <template x-for="(check, index) in checks" :key="index">
            <span :class="index < passed && 'is-on'"></span>
        </template>
    </div>

    <p class="sh-pw-summary" aria-live="polite">
        <span x-show="level === 'strong'">Strong password</span>
        <span x-show="level === 'fair'">Almost there</span>
        <span x-show="level === 'weak'">Weak password</span>
    </p>

    <ul class="sh-pw-list">
        <template x-for="(check, index) in checks" :key="index">
            <li :class="passes(check) && 'is-met'">
                <svg x-show="passes(check)" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                <svg x-show="! passes(check)" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="10" r="3"/></svg>
                <span x-text="check.label"></span>
                <span class="sh-pw-sr" x-text="passes(check) ? '(done)' : '(missing)'"></span>
            </li>
        </template>
    </ul>

    @if ($breachCheck)
        <p class="sh-pw-note">When you save, we also make sure this password hasn't appeared in a known data leak.</p>
    @endif
</div>
