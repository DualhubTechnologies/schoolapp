<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MTN MoMo's Collections API (momodeveloper.mtn.com): ask a phone to pay
 * ("request to pay" -- the owner gets a prompt and enters their PIN), then
 * ask MTN how it went. Keys and environment come from config/services.php.
 */
class MtnMomo
{
    public static function isConfigured(): bool
    {
        return filled(config('services.momo.subscription_key'))
            && filled(config('services.momo.api_user'))
            && filled(config('services.momo.api_key'));
    }

    public static function isSandbox(): bool
    {
        return config('services.momo.environment') === 'sandbox';
    }

    public static function currency(): string
    {
        return (string) config('services.momo.currency', 'UGX');
    }

    /**
     * The number as MTN wants it: 2567XXXXXXXX. Null when it is not a
     * Ugandan mobile number. The sandbox also takes MTN's test numbers.
     */
    public static function msisdn(?string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $phone);

        if (preg_match('/^(?:256|0)?(7\d{8})$/', $digits, $match)) {
            return '256'.$match[1];
        }

        return static::isSandbox() && strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * Send the prompt to the phone. MTN answers at once that it has the
     * request; whether it was paid comes later (status()).
     *
     * @throws RuntimeException when MTN cannot be reached or refuses it
     */
    public function requestToPay(string $reference, float $amount, string $msisdn, string $externalId, string $message, ?string $callbackUrl = null): void
    {
        $response = $this->send(fn () => $this->api()
            ->withToken($this->accessToken())
            ->withHeaders(array_filter([
                'X-Reference-Id' => $reference,
                'X-Callback-Url' => $callbackUrl,
            ]))
            ->post('/collection/v1_0/requesttopay', [
                'amount' => (string) round($amount),
                'currency' => static::currency(),
                'externalId' => $externalId,
                'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $msisdn],
                'payerMessage' => Str::limit($message, 150, ''),
                'payeeNote' => Str::limit($message, 150, ''),
            ]));

        if ($response->status() !== 202) {
            throw new RuntimeException('MTN MoMo did not accept the payment request (HTTP '.$response->status().'). Please try again.');
        }
    }

    /**
     * How a request to pay stands: PENDING, SUCCESSFUL or FAILED, with
     * the amount and currency MTN has for it.
     *
     * @return array{status: string, amount: ?float, currency: ?string, financial_transaction_id: ?string, reason: ?string}
     *
     * @throws RuntimeException when MTN cannot be reached
     */
    public function status(string $reference): array
    {
        $response = $this->send(fn () => $this->api()
            ->withToken($this->accessToken())
            ->get('/collection/v1_0/requesttopay/'.$reference));

        if (! $response->successful()) {
            throw new RuntimeException('MTN MoMo could not be asked about the payment (HTTP '.$response->status().').');
        }

        $reason = $response->json('reason');

        return [
            'status' => strtoupper((string) $response->json('status')),
            'amount' => is_numeric($response->json('amount')) ? (float) $response->json('amount') : null,
            'currency' => $response->json('currency'),
            'financial_transaction_id' => $response->json('financialTransactionId'),
            'reason' => is_array($reason) ? ($reason['message'] ?? $reason['code'] ?? null) : $reason,
        ];
    }

    /**
     * Sandbox only: make the API user and key that go in the .env file.
     *
     * @return array{api_user: string, api_key: string}
     */
    public function createSandboxUser(string $callbackHost): array
    {
        $user = (string) Str::uuid();

        $created = $this->send(fn () => $this->api()
            ->withHeaders(['X-Reference-Id' => $user])
            ->post('/v1_0/apiuser', ['providerCallbackHost' => $callbackHost]));

        if ($created->status() !== 201) {
            throw new RuntimeException('MTN did not create the sandbox user (HTTP '.$created->status().'). Check MOMO_COLLECTION_KEY.');
        }

        $key = $this->send(fn () => $this->api()->post("/v1_0/apiuser/{$user}/apikey"));

        if ($key->status() !== 201 || blank($key->json('apiKey'))) {
            throw new RuntimeException('MTN did not give an API key (HTTP '.$key->status().').');
        }

        return ['api_user' => $user, 'api_key' => (string) $key->json('apiKey')];
    }

    /** MTN's tokens last an hour; one is kept until just before then. */
    protected function accessToken(): string
    {
        $cacheKey = 'momo-token:'.md5(config('services.momo.api_user').config('services.momo.environment'));

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $response = $this->send(fn () => $this->api()
            ->withBasicAuth((string) config('services.momo.api_user'), (string) config('services.momo.api_key'))
            ->post('/collection/token/'));

        $token = (string) $response->json('access_token');

        if (! $response->successful() || $token === '') {
            throw new RuntimeException('MTN MoMo refused SchoolHub\'s keys (HTTP '.$response->status().'). Check the MoMo settings.');
        }

        Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    protected function api(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.momo.base_url'), '/'))
            ->acceptJson()
            ->timeout(30)
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => (string) config('services.momo.subscription_key'),
                'X-Target-Environment' => (string) config('services.momo.environment'),
            ]);
    }

    /**
     * @param  callable(): Response  $request
     */
    protected function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new RuntimeException('MTN MoMo could not be reached. Check the internet connection and try again.');
        }
    }
}
