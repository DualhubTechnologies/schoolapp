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
@endphp

<div class="sh-topbar">

    {{-- ---------- LEFT ---------- --}}
    <div class="sh-topbar-left">

        {{-- Sidebar collapse toggle. --}}
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

    </div>

    {{-- ---------- RIGHT ---------- --}}
    <div class="sh-topbar-right" x-data="{ open: false }">

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