<?php

namespace App\Jobs;

use App\Models\MessageBatch;
use App\Services\Messaging\BulkMessages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a bulk SMS in the background, so a message to hundreds of
 * families does not keep the school waiting on the page.
 */
class SendMessageBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /** Never retried: a retry would text the same families twice. */
    public int $tries = 1;

    public function __construct(public MessageBatch $batch) {}

    public function handle(BulkMessages $messages): void
    {
        if ($this->batch->status !== 'queued') {
            return;
        }

        $messages->send($this->batch);
    }

    /**
     * Stopped part-way (the worker died, the provider went down): keep the
     * counts so far and close the batch rather than leave it "sending".
     */
    public function failed(?Throwable $exception): void
    {
        $this->batch->refresh()->update(['status' => 'failed', 'finished_at' => now()]);
    }
}
