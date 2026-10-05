<?php

namespace App\Services\SchoolPay;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SchoolPay's transactions API (https://www.schoolpay.co.ug/apidocumentation):
 * the payments made to a school's learners over a range of days. Each
 * request is signed with MD5(school code + first date + API password).
 */
class SchoolPayClient
{
    public const BASE_URL = 'https://schoolpay.co.ug/paymentapi/AndroidRS';

    /** SchoolPay answers at most this many days per request. */
    public const MAX_DAYS = 31;

    /**
     * @return array{SCHOOL_FEES: array<mixed>, OTHER_FEES: array<mixed>}
     *
     * @throws RuntimeException when SchoolPay cannot be reached or refuses the request
     */
    public function transactions(string $schoolCode, string $password, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromDate = $from->toDateString();
        $hash = strtoupper(md5($schoolCode.$fromDate.$password));

        $response = Http::acceptJson()
            ->timeout(30)
            ->retry(2, 2000, throw: false)
            ->get(self::BASE_URL."/SchoolRangeTransactions/{$schoolCode}/{$fromDate}/{$to->toDateString()}/{$hash}");

        if (! $response->successful()) {
            throw new RuntimeException("SchoolPay could not be reached (HTTP {$response->status()}). Try again later.");
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('SchoolPay sent an answer SchoolHub could not read.');
        }

        $transactions = (array) ($body['transactions'] ?? []);
        $otherFees = (array) ($body['supplementaryFeePayments'] ?? []);
        $message = (string) ($body['returnMessage'] ?? '');

        // A non-zero code with nothing found just means no payments.
        if ((int) ($body['returnCode'] ?? -1) !== 0 && ! preg_match('/no\s+(transaction|record|payment)|not\s+found|^0\s+transaction/i', $message)) {
            throw new RuntimeException('SchoolPay refused the request: '.($message ?: 'check the school code and API password.'));
        }

        return ['SCHOOL_FEES' => $transactions, 'OTHER_FEES' => $otherFees];
    }
}
