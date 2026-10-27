<?php

namespace App\Services\Sync;

use RuntimeException;

/**
 * An incoming record links to a record that is not here yet (it comes
 * later, or waits on the review list), so it waits for the next round.
 */
class SyncWaiting extends RuntimeException
{
    public function __construct(public readonly string $for)
    {
        parent::__construct("Waiting for {$for}");
    }
}
