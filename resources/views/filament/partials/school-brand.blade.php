{{-- Sidebar brand for a signed-in school: its own logo and name (AppPanelProvider). --}}
<span class="sh-school-brand">
    @if ($logo)
        <img src="{{ $logo }}" alt="" class="sh-school-brand-logo">
    @else
        <span class="sh-school-brand-initials" aria-hidden="true">{{ $initials }}</span>
    @endif
    <span class="sh-school-brand-text">
        <span class="sh-school-brand-name">{{ $name }}</span>
        <span class="sh-school-brand-by">on SchoolHub</span>
    </span>
</span>
