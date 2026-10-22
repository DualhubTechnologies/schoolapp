<?php

namespace App\Services\Subscriptions;

use App\Models\MomoPayment;
use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use App\Services\Payments\MtnMomo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Pay now" on the Subscription page: MTN sends the school's phone a
 * prompt for the plan's price; once MTN says it was paid, for that amount,
 * the plan is renewed exactly as a payment recorded by SchoolHub would be.
 * Whatever reaches SchoolHub (the page checking, MTN's call-back, the
 * scheduled check) only makes it ask MTN again -- nothing is taken on trust.
 */
class MomoCheckout
{
    /** A prompt not answered within this many minutes is given up. */
    public const EXPIRES_AFTER_MINUTES = 30;

    public function __construct(protected MtnMomo $momo) {}

    /**
     * @throws RuntimeException with a message for the school
     */
    public function start(School $school, ?User $user, Plan $plan, string $cycle, string $phone): MomoPayment
    {
        if (! MtnMomo::isConfigured()) {
            throw new RuntimeException('Paying by phone is not set up yet. Please use one of the other ways to pay.');
        }

        if (! in_array($cycle, ['term', 'year'], true) || ! $plan->is_active || $plan->is_trial) {
            throw new RuntimeException('Choose a plan and whether to pay for a term or a year.');
        }

        $amount = $plan->priceFor($cycle);
        $msisdn = MtnMomo::msisdn($phone);

        if ($amount <= 0) {
            throw new RuntimeException('This plan has no price for that period. Please contact SchoolHub.');
        }

        if ($msisdn === null) {
            throw new RuntimeException('Enter an MTN mobile money number, e.g. 0772 123456.');
        }

        $waiting = MomoPayment::where('school_id', $school->getKey())
            ->where('status', MomoPayment::PENDING)
            ->where('created_at', '>', now()->subMinutes(2))
            ->exists();

        if ($waiting) {
            throw new RuntimeException('A payment prompt was sent less than two minutes ago. Approve it on the phone, or wait a moment and try again.');
        }

        $payment = MomoPayment::create([
            'school_id' => $school->getKey(),
            'user_id' => $user?->getKey(),
            'plan_id' => $plan->getKey(),
            'cycle' => $cycle,
            'amount' => $amount,
            'currency' => MtnMomo::currency(),
            'phone' => $msisdn,
            'reference' => (string) Str::uuid(),
            'status' => MomoPayment::PENDING,
        ]);

        try {
            $this->momo->requestToPay(
                $payment->reference,
                $amount,
                $msisdn,
                'SH-'.$payment->getKey(),
                "SchoolHub {$plan->name}, 1 {$cycle}",
                $this->callbackUrl($payment),
            );
        } catch (RuntimeException $e) {
            $payment->update(['status' => MomoPayment::FAILED, 'reason' => $e->getMessage(), 'completed_at' => now()]);

            throw $e;
        }

        return $payment;
    }

    /**
     * Ask MTN how the payment stands and act on the answer: renew the plan
     * once, when paid in full; record why, when it failed.
     */
    public function refresh(MomoPayment $payment): MomoPayment
    {
        if (! $payment->isPending()) {
            return $payment;
        }

        try {
            $answer = $this->momo->status($payment->reference);
        } catch (RuntimeException) {
            return $this->expireIfOld($payment);
        }

        if ($answer['status'] === 'SUCCESSFUL') {
            return $this->settle($payment, $answer);
        }

        if ($answer['status'] === 'FAILED') {
            return $this->fail($payment, $this->reasonFor($answer['reason']));
        }

        return $this->expireIfOld($payment);
    }

    /**
     * @param  array{status: string, amount: ?float, currency: ?string, financial_transaction_id: ?string, reason: ?string}  $answer
     */
    protected function settle(MomoPayment $payment, array $answer): MomoPayment
    {
        if ($answer['amount'] === null || abs($answer['amount'] - round($payment->amount)) > 0.01 || $answer['currency'] !== $payment->currency) {
            return $this->fail($payment, 'MTN reported a different amount ('.($answer['currency'] ?? '').' '.($answer['amount'] ?? '?').'). SchoolHub has been told and will sort it out.');
        }

        return DB::transaction(function () use ($payment, $answer): MomoPayment {
            $locked = MomoPayment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            // Already settled by the page, the call-back or the scheduler.
            if (! $locked->isPending()) {
                return $locked;
            }

            $subscription = SubscriptionManager::renew(
                $locked->school,
                $locked->plan,
                $locked->cycle,
                $locked->amount,
                [
                    'amount' => $locked->amount,
                    'method' => 'mobile_money',
                    'reference' => 'MTN '.($answer['financial_transaction_id'] ?: $locked->reference),
                    'notes' => 'Paid by phone ('.$locked->phone.') with Pay now.',
                ],
            );

            $locked->update([
                'status' => MomoPayment::SUCCESSFUL,
                'financial_transaction_id' => $answer['financial_transaction_id'],
                'subscription_id' => $subscription->getKey(),
                'completed_at' => now(),
            ]);

            return $locked;
        });
    }

    protected function fail(MomoPayment $payment, string $reason): MomoPayment
    {
        MomoPayment::whereKey($payment->getKey())
            ->where('status', MomoPayment::PENDING)
            ->update(['status' => MomoPayment::FAILED, 'reason' => $reason, 'completed_at' => now()]);

        return $payment->fresh() ?? $payment;
    }

    protected function expireIfOld(MomoPayment $payment): MomoPayment
    {
        return $payment->created_at->lt(now()->subMinutes(self::EXPIRES_AFTER_MINUTES))
            ? $this->fail($payment, 'The prompt was not answered in time. Nothing was taken; please try again.')
            : $payment;
    }

    /** MTN's reasons, in words a bursar understands. */
    protected function reasonFor(?string $reason): string
    {
        return match (strtoupper((string) $reason)) {
            'NOT_ENOUGH_FUNDS', 'INSUFFICIENT_FUNDS' => 'There was not enough money on the mobile money account.',
            'APPROVAL_REJECTED', 'REJECTED', 'PAYER_REJECTED' => 'The payment was declined on the phone.',
            'EXPIRED', 'TIMEOUT' => 'The prompt was not answered in time. Nothing was taken; please try again.',
            'PAYER_NOT_FOUND', 'PAYEE_NOT_FOUND' => 'That number is not registered for MTN Mobile Money.',
            default => 'MTN did not complete the payment'.($reason ? " ({$reason})" : '').'. Nothing was taken; please try again.',
        };
    }

    /** MTN calls back only to a public https address. */
    protected function callbackUrl(MomoPayment $payment): ?string
    {
        $url = route('momo.callback', $payment->reference);

        return str_starts_with($url, 'https://') ? $url : null;
    }
}
