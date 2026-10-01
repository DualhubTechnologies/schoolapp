<?php

namespace App\Services;

use App\Models\SmsOutbox;
use App\Support\Edition;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one SMS through the configured driver (see config/sms.php).
 *
 * Returns ['ok' => bool, 'ref' => ?string, 'error' => ?string] rather than
 * throwing, so one bad number never stops a batch of reminders.
 *
 * In the Windows app, a text that cannot reach Africa's Talking because
 * the computer is offline goes to the outbox (ref "queued") and is sent by
 * `sms:send-queued` once it is back online. After the first connection
 * failure the rest of the request's texts go straight to the outbox, so a
 * class's reminders do not each wait for the network to time out.
 */
class SmsSender
{
    /** Set once a send in this request has found the computer offline. */
    protected static bool $offline = false;

    /**
     * @return array{ok: bool, ref: ?string, error: ?string}
     */
    public function send(string $phone, string $message): array
    {
        $to = static::normalisePhone($phone);

        if (! $to) {
            return ['ok' => false, 'ref' => null, 'error' => "Invalid phone number: {$phone}"];
        }

        if (config('sms.driver') !== 'africastalking') {
            return $this->viaLog($to, $message);
        }

        if (Edition::isDesktop() && static::$offline) {
            return $this->queue($to, $message);
        }

        $result = $this->viaAfricasTalking($to, $message);

        if (Edition::isDesktop() && ($result['offline'] ?? false)) {
            static::$offline = true;

            return $this->queue($to, $message);
        }

        return ['ok' => $result['ok'], 'ref' => $result['ref'], 'error' => $result['error']];
    }

    /**
     * Send without the outbox: for `sms:send-queued`, which decides itself
     * what to do when the computer is still offline.
     *
     * @return array{ok: bool, ref: ?string, error: ?string, offline?: bool}
     */
    public function sendNow(string $to, string $message): array
    {
        return config('sms.driver') === 'africastalking'
            ? $this->viaAfricasTalking($to, $message)
            : $this->viaLog($to, $message);
    }

    /** @return array{ok: bool, ref: ?string, error: ?string} */
    protected function queue(string $to, string $message): array
    {
        SmsOutbox::create(['to' => $to, 'message' => $message]);

        return ['ok' => true, 'ref' => 'queued', 'error' => null];
    }

    /** Forget that this request found the computer offline (for tests and long runs). */
    public static function resetOffline(): void
    {
        static::$offline = false;
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
            str_starts_with($digits, '0') => $cc.substr($digits, 1),
            strlen($digits) === 9 => $cc.$digits,
            default => $digits,
        };

        return preg_match('/^'.$cc.'7\d{8}$/', $digits) ? '+'.$digits : null;
    }

    /** @return array{ok: bool, ref: ?string, error: ?string} */
    protected function viaLog(string $to, string $message): array
    {
        Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);

        return ['ok' => true, 'ref' => 'log', 'error' => null];
    }

    /**
     * @return array{ok: bool, ref: ?string, error: ?string, offline?: bool}
     */
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
        } catch (ConnectionException $e) {
            // No internet (or Africa's Talking unreachable): not the number's fault.
            return ['ok' => false, 'ref' => null, 'error' => $e->getMessage(), 'offline' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ref' => null, 'error' => $e->getMessage()];
        }
    }
}
