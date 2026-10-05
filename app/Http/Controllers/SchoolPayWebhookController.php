<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolPayTransaction;
use App\Services\SchoolPay\SchoolPayPayments;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Where SchoolPay posts each payment made to a school's learners, the
 * moment it is made. The address carries the school's own secret
 * (School::schoolPayWebhookUrl()), and each post is signed with
 * SHA256(API password + SchoolPay receipt number), checked before anything
 * is recorded. SchoolPay sends each payment once and only looks at the
 * HTTP status; the nightly check (schoolpay:sync) catches any missed.
 */
class SchoolPayWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, SchoolPayPayments $payments): Response
    {
        $school = School::where('schoolpay_webhook_token', $token)->first();

        if (! $school || ! $school->usesSchoolPay()) {
            abort(404);
        }

        $payment = $request->input('payment');

        if (! is_array($payment) || trim((string) ($payment['schoolpayReceiptNumber'] ?? '')) === '') {
            abort(422, 'No payment in the notification.');
        }

        $receipt = trim((string) $payment['schoolpayReceiptNumber']);

        $expected = hash('sha256', $school->schoolpay_api_password.$receipt);

        abort_unless(hash_equals($expected, strtolower(trim((string) $request->input('signature')))), 403, 'Signature does not match.');

        $type = $request->input('type') === 'OTHER_FEES' ? SchoolPayTransaction::TYPE_OTHER_FEES : SchoolPayTransaction::TYPE_SCHOOL_FEES;

        $payments->ingest($school, $payment, $type, 'webhook');

        return response('OK', 200);
    }
}
