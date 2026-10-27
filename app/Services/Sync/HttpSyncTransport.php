<?php

namespace App\Services\Sync;

use Illuminate\Http\Client\ConnectionException;
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
        $request = Http::acceptJson()->connectTimeout(10)->timeout(120);

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        $response = $request->post(rtrim((string) config('sync.url'), '/').'/sync/'.$action, $payload);

        if ($response->failed()) {
            throw new RuntimeException((string) ($response->json('message') ?: 'Online SchoolHub could not sync (error '.$response->status().'). Try again later.'));
        }

        return (array) $response->json();
    }

    /** Whether online SchoolHub can be reached right now (a quick check, a few seconds at most). */
    public static function reachable(): bool
    {
        try {
            return Http::connectTimeout(4)->timeout(6)->get(rtrim((string) config('sync.url'), '/').'/up')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
