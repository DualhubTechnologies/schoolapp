<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The Windows app talking to the online server (SyncController) over the
 * internet. A computer that is offline gets a ConnectionException.
 */
class HttpSyncTransport implements SyncTransport
{
    public function post(string $action, array $payload, ?string $token = null): array
    {
        $request = Http::acceptJson()->timeout(120);

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        $response = $request->post(rtrim((string) config('sync.url'), '/').'/sync/'.$action, $payload);

        if ($response->failed()) {
            throw new RuntimeException((string) ($response->json('message') ?: 'Online SchoolHub could not sync (error '.$response->status().'). Try again later.'));
        }

        return (array) $response->json();
    }
}
