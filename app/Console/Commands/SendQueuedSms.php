<?php

namespace App\Console\Commands;

use App\Models\SmsOutbox;
use App\Services\SmsSender;
use Illuminate\Console\Command;

/**
 * The Windows app's outbox: texts written while the computer was offline
 * (SmsSender), sent oldest first once it is back online. Still offline:
 * stops and tries again next time. A real failure (wrong number, no
 * credit) counts an attempt; after SmsOutbox::MAX_ATTEMPTS the text is
 * given up on.
 */
class SendQueuedSms extends Command
{
    protected $signature = 'sms:send-queued {--limit=200 : Texts to try in one run}';

    protected $description = 'Send texts that waited in the outbox while the computer was offline';

    public function handle(SmsSender $sms): int
    {
        $sent = 0;
        $failed = 0;

        $waiting = SmsOutbox::query()->waiting()->oldest('id')->limit(max(1, (int) $this->option('limit')))->get();

        foreach ($waiting as $text) {
            $result = $sms->sendNow($text->to, $text->message);

            if ($result['offline'] ?? false) {
                $this->line('Still offline; the remaining texts wait for the next run.');

                break;
            }

            if ($result['ok']) {
                $text->update(['sent_at' => now(), 'ref' => $result['ref'], 'last_error' => null, 'attempts' => $text->attempts + 1]);
                $sent++;

                continue;
            }

            $attempts = $text->attempts + 1;
            $text->update([
                'attempts' => $attempts,
                'last_error' => mb_substr((string) $result['error'], 0, 255),
                'failed_at' => $attempts >= SmsOutbox::MAX_ATTEMPTS ? now() : null,
            ]);
            $failed++;
        }

        $this->info("Sent {$sent}, failed {$failed}, waiting ".SmsOutbox::query()->waiting()->count().'.');

        return self::SUCCESS;
    }
}
