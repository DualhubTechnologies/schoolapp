@php $findings = $this->findings(); @endphp

<x-filament-panels::page>
    <section class="shi-page">
        @if ($findings)
            @include('filament.app.partials.insights-list', ['findings' => $findings])
        @else
            <div class="shi-clear">
                <x-filament::icon icon="heroicon-o-check-badge" class="shi-clear-icon" />
                <strong>Nothing needs your attention right now</strong>
                <span>Payments, fee collection and marks all look as expected.</span>
            </div>
        @endif
    </section>

    <section class="shi-about">
        <h3>What is looked at</h3>
        <ul>
            <li>Money received today, and fee collection against last term at the same week.</li>
            <li>Learners who have paid nothing this term, and families who always pay but have not yet.</li>
            <li>The same amount recorded twice for a learner on the same day.</li>
            <li>Learners whose average fell ten points or more since last term.</li>
        </ul>
    </section>

    <style>
        .shi-page { background: #fff; border: 1px solid var(--sh-border, #e4e8f0); border-radius: 14px; padding: .4rem 1.25rem; }
        .shi-clear { display: grid; justify-items: center; gap: .3rem; padding: 2.5rem 1rem; text-align: center; }
        .shi-clear-icon { width: 2.5rem; height: 2.5rem; color: #047857; }
        .shi-clear strong { font-size: .95rem; color: #16233a; }
        .shi-clear span { font-size: .85rem; color: #64748b; }
        .shi-about { padding: 0 .25rem; }
        .shi-about h3 { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: .4rem; }
        .shi-about ul { list-style: disc; padding-left: 1.1rem; display: grid; gap: .2rem; font-size: .82rem; color: #475569; }
    </style>
</x-filament-panels::page>
