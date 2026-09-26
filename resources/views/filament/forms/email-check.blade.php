{{--
    Live hints under an email field (App\Support\EmailCheck::apply).
    While the field has focus and the address is incomplete, a gentle hint
    shows the expected shape. Once the person leaves the field, a mistyped
    common domain (gmial.com) gets a one-click "Did you mean …?" fix, and
    the server checks the field for real errors such as "already registered".
--}}
<div
    x-data="{
        domains: @js($domains),
        focused: false,
        get value() { return ($wire.$get(@js($statePath)) ?? '').trim() },
        get valid() { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.value) },
        distance(a, b) {
            const row = Array.from({ length: b.length + 1 }, (_, i) => i)
            for (let i = 1; i <= a.length; i++) {
                let previous = row[0]
                row[0] = i
                for (let j = 1; j <= b.length; j++) {
                    const current = row[j]
                    row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1))
                    previous = current
                }
            }
            return row[b.length]
        },
        get suggestion() {
            const at = this.value.lastIndexOf('@')
            if (at < 1) return null
            const domain = this.value.slice(at + 1).toLowerCase()
            if (domain.length < 4 || this.domains.includes(domain)) return null
            const closest = this.domains
                .map((candidate) => [candidate, this.distance(domain, candidate)])
                .sort((a, b) => a[1] - b[1])[0]
            return closest[1] <= 2 ? this.value.slice(0, at + 1) + closest[0] : null
        },
        accept() { $wire.$set(@js($statePath), this.suggestion) },
    }"
    x-init="
        const field = $el.closest('.fi-fo-field')
        field?.addEventListener('focusin', () => focused = true)
        field?.addEventListener('focusout', () => focused = false)
    "
    x-show="value.length > 0 && ((focused && ! valid) || (! focused && suggestion))"
    x-cloak
    class="sh-email"
    aria-live="polite"
>
    <p x-show="focused && ! valid" class="sh-email-hint">
        Keep typing — an email looks like <strong>name@example.com</strong>
    </p>

    <p x-show="! focused && suggestion" class="sh-email-suggest">
        Did you mean
        <button type="button" x-on:mousedown.prevent x-on:click="accept()" x-text="suggestion"></button>?
    </p>
</div>
