<?php

namespace App\Http\Controllers;

use App\Models\MomoPayment;
use App\Services\Subscriptions\MomoCheckout;
use Illuminate\Http\Response;

/**
 * Where MTN calls when a "Pay now" prompt has been answered. What MTN
 * posts is not trusted: it only makes SchoolHub ask MTN for the payment's
 * status (MomoCheckout::refresh), which renews the plan if it was paid.
 */
class MomoCallbackController extends Controller
{
    public function __invoke(string $reference, MomoCheckout $checkout): Response
    {
        $payment = MomoPayment::where('reference', $reference)->first();

        abort_if($payment === null, 404);

        $checkout->refresh($payment);

        return response('OK', 200);
    }
}
