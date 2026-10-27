<?php

namespace App\Http\Controllers;

use App\Models\SyncDevice;
use App\Services\Sync\SyncServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Where a linked Windows app syncs with the school's online account
 * (SyncServer). Online server only; the computer signs each request with
 * the token it got when it was linked. Not a browser form: no CSRF token
 * (bootstrap/app.php); rate limited instead.
 */
class SyncController extends Controller
{
    public function __construct(protected SyncServer $server) {}

    public function link(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return response()->json($this->server->link($data['code'], $data['name'] ?? null));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function connect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return response()->json($this->server->connect($data['code'], $data['email'], $data['password'], $data['name'] ?? null));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function pull(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $data = $request->validate([
            'cursor' => ['required', 'integer', 'min:0'],
            'scan' => ['boolean'],
            'fetch' => ['array', 'max:500'],
            'fetch.*' => ['uuid'],
            'school' => ['array'],
            'school.name' => ['nullable', 'string', 'max:150'],
            'school.code' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json($this->server->pull($device, (int) $data['cursor'], (bool) ($data['scan'] ?? true), array_values($data['fetch'] ?? []), (array) ($data['school'] ?? [])));
    }

    public function push(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $data = $request->validate([
            'cursor' => ['required', 'integer', 'min:0'],
            'changes' => ['present', 'array', 'max:1000'],
        ]);

        return response()->json(['results' => $this->server->push($device, (int) $data['cursor'], array_values($data['changes']))]);
    }

    protected function device(Request $request): SyncDevice
    {
        $device = $this->server->device($request->bearerToken());

        abort_if($device === null, 401, 'This computer is no longer linked. Link it again with a new code from Settings → Windows app online.');

        return $device;
    }
}
