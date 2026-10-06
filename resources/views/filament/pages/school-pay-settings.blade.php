<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <div class="sp-brand">
            <img src="{{ asset('images/schoolpay-logo.png') }}" alt="SchoolPay" width="180" height="78">
            <p>Parents pay school fees with SchoolPay at banks, mobile money and agents. Connect it here and SchoolHub records each payment on the learner's account for you.</p>
        </div>

        {{ $this->form }}

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">How to set it up</x-slot>
            <ol class="list-decimal space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300">
                <li>Ask SchoolPay (support@schoolpay.co.ug) for your school's <strong>API password</strong>. It is not your SchoolPay login password.</li>
                <li>Turn on <strong>Record SchoolPay payments automatically</strong>, enter your school code and the API password, and save.</li>
                <li>Once the API password is saved, a <strong>web hook address</strong> appears above. Copy it into "Web Hook URL" in your SchoolPay school portal and enable web hooks.</li>
                <li>Make sure each learner's <strong>SchoolPay code</strong> (or admission number) is on their record, so payments land on the right account.</li>
            </ol>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                Payments appear under
                <x-filament::link :href="$this->paymentsUrl()">Fees → SchoolPay payments</x-filament::link>.
                Any SchoolPay could not match to a learner wait there for you to choose who paid.
            </p>
        </x-filament::section>

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-m-check">Save</x-filament::button>
        </div>
    </form>
    <style>
        .sp-brand { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.5rem; padding: 1rem 1.25rem; background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; }
        .sp-brand img { width: 180px; height: auto; flex: none; }
        .sp-brand p { flex: 1 1 16rem; margin: 0; font-size: .9rem; color: #475569; }
        .dark .sp-brand { background: rgb(17 24 39); border-color: rgb(255 255 255 / .1); }
        .dark .sp-brand img { background: #fff; border-radius: 8px; padding: .3rem; }
        .dark .sp-brand p { color: #cbd5e1; }
    </style>
</x-filament-panels::page>
