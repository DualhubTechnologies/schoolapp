<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one SMS through the configured driver (see config/sms.php).
 *
 * Returns ['ok' => bool, 'ref' => ?string, 'error' => ?string] rather than
 * throwing, so one bad number never stops a batch of reminders.
 */
class SmsSender
{
    /**
     * @return array{ok: bool, ref: ?string, error: ?string}
     */
    public function send(string $phone, string $message): array
    {
        $to = static::normalisePhone($phone);

        if (! $to) {
            return ['ok' => false, 'ref' => null, 'error' => "Invalid phone number: {$phone}"];
        }

        return match (config('sms.driver')) {
            'africastalking' => $this->viaAfricasTalking($to, $message),
            default => $this->viaLog($to, $message),
        };
    }

    /**
     * 0772 123456 / 772123456 / +256772123456 -> +256772123456.
     * Returns null for anything that is not a plausible mobile number.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $cc = config('sms.country_code', '256');

        $digits = match (true) {
            str_starts_with($digits, $cc) => $digits,
            str_starts_with($digits, '0') => $cc . substr($digits, 1),
            strlen($digits) === 9 => $cc . $digits,
            default => $digits,
        };

        return preg_match('/^' . $cc . '7\d{8}$/', $digits) ? '+' . $digits : null;
    }

    protected function viaLog(string $to, string $message): array
    {
        Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);

        return ['ok' => true, 'ref' => 'log', 'error' => null];
    }

    protected function viaAfricasTalking(string $to, string $message): array
    {
        $config = config('sms.africastalking');

        if (! $config['api_key']) {
            return ['ok' => false, 'ref' => null, 'error' => 'Africa\'s Talking API key is not set.'];
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withHeaders(['apiKey' => $config['api_key']])
                ->timeout(15)
                ->post($config['endpoint'], array_filter([
                    'username' => $config['username'],
                    'to' => $to,
                    'message' => $message,
                    'from' => $config['sender_id'],
                ]));

            $recipient = $response->json('SMSMessageData.Recipients.0');

            if ($response->successful() && in_array($recipient['statusCode'] ?? null, [100, 101, 102], true)) {
                return ['ok' => true, 'ref' => $recipient['messageId'] ?? null, 'error' => null];
            }

            return [
                'ok' => false,
                'ref' => null,
                'error' => $recipient['status'] ?? $response->json('SMSMessageData.Message') ?? "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ref' => null, 'error' => $e->getMessage()];
        }
    }
}
