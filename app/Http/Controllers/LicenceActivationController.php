<?php

namespace App\Http\Controllers;

use App\Support\Licensing\LicenceIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Where the Windows app swaps a short licence code (FGDH-FWFH-2342-WETR)
 * for its school's signed licence key, once, when the school enters it.
 * Online server only; rate limited (routes/web.php).
 */
class LicenceActivationController extends Controller
{
    public function __invoke(Request $request, LicenceIssuer $issuer): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'school_name' => ['required', 'string', 'max:150'],
            'school_code' => ['required', 'string', 'max:20'],
        ]);

        try {
            $key = $issuer->activate($data['code'], $data['school_name'], $data['school_code']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['key' => $key]);
    }
}
