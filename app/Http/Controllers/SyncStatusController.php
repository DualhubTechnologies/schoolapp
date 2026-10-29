<?php

namespace App\Http\Controllers;

use App\Support\Edition;
use App\Support\SyncIndicator;
use Illuminate\Http\JsonResponse;

/** The Windows app's top bar asks how syncing is going (SyncIndicator). */
class SyncStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        abort_unless(Edition::isDesktop(), 404);

        return response()->json(SyncIndicator::now());
    }
}
