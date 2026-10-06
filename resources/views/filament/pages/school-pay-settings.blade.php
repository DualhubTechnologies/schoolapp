<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">How to set it up</x-slot>
            <ol class="list-decimal space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300">
                <li>Ask SchoolPay (support@schoolpay.co.ug) for your school's <strong>API password</strong>. It is not your SchoolPay login password.</li>
                <li>Turn on <strong>Record SchoolPay payments automatically</strong>, enter your school code and the API password, and save.</li>
                <li>Copy the <strong>web hook address</strong> shown above into "Web Hook URL" in your SchoolPay school portal and enable web hooks.</li>
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
</x-filament-panels::page>
