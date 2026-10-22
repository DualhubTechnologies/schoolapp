<?php

namespace App\Console\Commands;

use App\Models\MomoPayment;
use App\Services\Subscriptions\MomoCheckout;
use Illuminate\Console\Command;

/**
 * The safety net under MTN's call-back and the page's own checks: asks
 * MTN about every "Pay now" still waiting, so a school that paid and
 * closed the page still gets its plan, and unanswered prompts are closed.
 */
class MomoCheckPending extends Command
{
    protected $signature = 'momo:check-pending';

    protected $description = 'Ask MTN MoMo about subscription payments still waiting';

    public function handle(MomoCheckout $checkout): int
    {
        $waiting = MomoPayment::where('status', MomoPayment::PENDING)
            ->where('created_at', '<', now()->subMinute())
            ->get();

        foreach ($waiting as $payment) {
            $payment = $checkout->refresh($payment);
            $this->line("{$payment->reference}: {$payment->status}");
        }

        return self::SUCCESS;
    }
}
