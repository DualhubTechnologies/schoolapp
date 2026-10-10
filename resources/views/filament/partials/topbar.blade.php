{{--
    SchoolHub — custom topbar
    Save as: resources/views/filament/partials/topbar.blade.php

    This REPLACES Filament's topbar completely. Filament's own bar is
    hidden in CSS (.fi-topbar { display: none }) and this is injected
    via the PAGE_START render hook instead.

    Layout:
      LEFT   sidebar toggle · date + live clock · role badge
      RIGHT  user menu with sign out
--}}

@php
    $user     = auth()->user();
    $panelId  = filament()->getCurrentPanel()->getId();
    $logout   = route("filament.{$panelId}.auth.logout");
    $role     = $user?->getRoleNames()->first();
    $initials = method_exists($user, 'initials') ? $user->initials() : mb_substr($user?->name ?? '?', 0, 2);

    // Subscribed plan chip: schools only (the platform owner has no plan of its own).
    // On trial it just says "Trial" -- once there is a real paid plan (active,
    // or overdue on one) the chip names that plan instead.
    // Parents have nothing to do with the school's plan.
    $subStatus = $user?->school_id && ! $user->hasRole('Parent') ? \App\Services\Subscriptions\SubscriptionManager::status($user->school_id) : null;
    $plan      = $subStatus['plan'] ?? null;
    $planState = $subStatus['state'] ?? null;
    // Before approval there is no trial yet, whatever plan is on file.
    $planLabel = match ($planState) {
        'trial' => 'Trial',
        'pending' => 'Awaiting approval',
        'rejected' => 'Not approved',
        default => $plan?->name,
    };
    $planTone  = match ($planState) {
        'trial' => 'blue',
        'active' => 'green',
        'pending' => 'amber',
        default => 'red', // grace, expired, none, suspended, rejected
    };
    $canManageSub = $plan && \App\Support\Modules::hasFullAccess($user)
        && ! in_array($planState, \App\Filament\Pages\AwaitingApproval::STATUSES, true);
    // No sidebar for a school held on its Subscription page (AppPanelProvider).
    $hasSidebar = filament()->hasNavigation();
@endphp

<div class="sh-topbar">

    {{-- ---------- LEFT ---------- --}}
    <div class="sh-topbar-left">

        {{-- Sidebar collapse toggle. --}}
        @if ($hasSidebar)
        <button type="button"
                class="sh-icon-btn"
                title="Toggle sidebar"
                x-data
                @click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
        @endif

        {{-- Phones and tablets: the school's logo and name (the sidebar, where
             they sit on wide screens, opens below this bar there). --}}
        @if ($topbarBrand = \App\Providers\Filament\AppPanelProvider::schoolBrand())
            <div class="sh-topbar-brand">{{ $topbarBrand }}</div>
        @endif

        {{-- Date + live clock --}}
        <div class="sh-chip" x-data="{
                time: '',
                tick() { this.time = new Date().toLocaleTimeString('en-GB', { hour12: false }); }
             }"
             x-init="tick(); setInterval(() => tick(), 1000)">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            <span class="sh-chip-value">{{ now()->format('l, j F Y') }}</span>
            <span class="sh-chip-divider"></span>
            <span class="sh-chip-clock" x-text="time"></span>
        </div>

        {{-- Role badge --}}
        @if ($role)
            <div class="sh-chip sh-chip-role">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751A11.959 11.959 0 0112 2.714z" />
                </svg>
                <span>ROLE</span>
                <span class="sh-chip-value">{{ $role }}</span>
            </div>
        @endif

        {{-- Subscribed plan --}}
        @if ($plan)
            @php $tag = $canManageSub ? 'a' : 'div'; @endphp
            <{{ $tag }}
                @if ($canManageSub) href="{{ \App\Filament\Pages\SchoolSubscription::getUrl() }}" @endif
                @class(['sh-chip', 'sh-chip-plan', "sh-chip-plan-{$planTone}", 'sh-chip-link' => $canManageSub])
            >
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3M3.75 19.5h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                </svg>
                <span>PLAN</span>
                <span class="sh-chip-value">{{ $planLabel }}</span>
            </{{ $tag }}>
        @endif

    </div>

    {{-- ---------- RIGHT ---------- --}}
    <div class="sh-topbar-right" x-data="{ open: false }">

        {{-- Help: a WhatsApp chat with the SchoolHub team, saying who is asking and from which page. --}}
        @if ($user?->school_id)
            @php
                $helpText = "Hello SchoolHub, I need help. I'm {$user->name} at " . ($user->school?->name ?? 'my school') . '. Page: ' . request()->url();
            @endphp
            <a href="https://wa.me/{{ config('contact.whatsapp') }}?text={{ rawurlencode($helpText) }}"
               target="_blank" rel="noopener"
               class="sh-icon-btn sh-help-btn"
               title="Get help on WhatsApp ({{ config('contact.phone') }})">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                <span class="sh-help-label">Help</span>
            </a>
        @endif

        {{-- Needs-attention bell: every item is a link to where it is dealt with. --}}
        @php
            $attention = $user ? \App\Services\AttentionItems::for($user) : [];
            $attentionCount = count($attention);
            $attentionTone = collect($attention)->contains('tone', 'danger') ? 'danger' : ($attentionCount ? 'warning' : null);
        @endphp
        <div class="sh-bell" x-data="{ bell: false }" @keydown.escape.window="bell = false">
            <button type="button"
                    class="sh-icon-btn sh-bell-btn"
                    :class="bell && 'is-active'"
                    @click="bell = !bell; open = false"
                    @click.outside="bell = false"
                    aria-haspopup="true"
                    :aria-expanded="bell"
                    aria-label="{{ $attentionCount ? "{$attentionCount} " . str('item')->plural($attentionCount) . ' need attention' : 'Nothing needs attention' }}">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                @if ($attentionCount)
                    <span class="sh-bell-badge sh-bell-badge-{{ $attentionTone }}">{{ $attentionCount > 9 ? '9+' : $attentionCount }}</span>
                @endif
            </button>

            <div class="sh-bell-menu" x-show="bell" x-cloak x-transition.opacity.duration.150ms @click.outside="bell = false">
                <div class="sh-bell-head">
                    <strong>Needs attention</strong>
                    @if ($attentionCount)<span>{{ $attentionCount }}</span>@endif
                </div>

                @forelse ($attention as $item)
                    <a href="{{ $item['url'] }}" class="sh-bell-item">
                        <span class="sh-bell-icon sh-bell-icon-{{ $item['tone'] }}">
                            <x-filament::icon :icon="$item['icon']" />
                        </span>
                        <span class="sh-bell-text">
                            <span class="sh-bell-title">{{ $item['title'] }}</span>
                            @if ($item['detail'])<span class="sh-bell-detail">{{ $item['detail'] }}</span>@endif
                        </span>
                        <svg class="sh-bell-go" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </a>
                @empty
                    <div class="sh-bell-empty">
                        <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <strong>You're all caught up</strong>
                        <span>Nothing needs your attention right now.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <button type="button"
                class="sh-user-btn"
                @click="open = !open"
                @click.outside="open = false">
            <span class="sh-user-avatar">{{ $initials }}</span>
            <span class="sh-user-meta">
                <span class="sh-user-name">{{ $user?->name }}</span>
                @if ($role)
                    <span class="sh-user-role">{{ $role }}</span>
                @endif
            </span>
            <svg class="sh-user-chevron" :class="open && 'sh-rotated'"
                 width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div class="sh-user-menu" x-show="open" x-cloak x-transition.opacity.duration.150ms>

            <div class="sh-user-menu-head">
                <div class="sh-user-menu-name">{{ $user?->name }}</div>
                <div class="sh-user-menu-email">{{ $user?->email }}</div>
            </div>

            @if (filament()->hasProfile())
                <a href="{{ filament()->getProfileUrl() }}" class="sh-user-menu-item">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    My profile &amp; security
                </a>
            @endif

            <form method="POST" action="{{ $logout }}">
                @csrf
                <button type="submit" class="sh-user-menu-item">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Sign out
                </button>
            </form>

        </div>
    </div>

</div>