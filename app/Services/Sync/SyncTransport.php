<?php

namespace App\Services\Sync;

/**
 * How the Windows app reaches the online server (HttpSyncTransport; tests
 * swap in one that calls the server directly).
 */
interface SyncTransport
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $action, array $payload, ?string $token = null): array;
}
