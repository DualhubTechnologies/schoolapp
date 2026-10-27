<div>
    @if ($state === 'running')
        <div class="sh-first-sync" wire:init="runSync" role="status">
            <span class="sh-first-spin" aria-hidden="true"></span>
            <span><b>Bringing your school's records from online…</b> You are signed in and can start working; records appear as they arrive. Keep SchoolHub open and the internet on.</span>
        </div>
    @elseif ($state === 'waiting')
        <div class="sh-first-sync sh-first-wait" wire:poll.15s="runSync" role="status">
            <span class="sh-first-spin" aria-hidden="true"></span>
            <span><b>Still bringing your school's records…</b> Waiting for the internet (or a sync already running); it carries on by itself.</span>
        </div>
    @else
        <div class="sh-first-sync sh-first-done" role="status">
            <span>✓ <b>All your school's records are here</b>, and they keep syncing with online.</span>
            <a href="{{ url()->current() }}" onclick="window.location.reload(); return false;">Show them</a>
        </div>
    @endif

    @once
        <style>
            .sh-first-sync { display: flex; align-items: center; gap: .65rem; margin: 0 0 1rem; padding: .7rem 1rem; border-radius: 10px; border: 1px solid #bfdbfe; background: #eff6ff; color: #1e3a8a; font-size: .875rem; }
            .sh-first-wait { border-color: #fde68a; background: #fffbeb; color: #92400e; }
            .sh-first-done { border-color: #bbf7d0; background: #f0fdf4; color: #166534; justify-content: space-between; }
            .sh-first-done a { font-weight: 700; color: inherit; text-decoration: underline; white-space: nowrap; }
            .sh-first-spin { flex: none; width: 1rem; height: 1rem; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: sh-first-spin .8s linear infinite; }
            @keyframes sh-first-spin { to { transform: rotate(360deg); } }
            @media (prefers-reduced-motion: reduce) { .sh-first-spin { animation-duration: 2.4s; } }
        </style>
    @endonce
</div>
