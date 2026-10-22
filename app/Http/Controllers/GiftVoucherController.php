<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionActivationCode;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Activation codes printed as gift vouchers, four to an A4 page, to cut
 * out and hand to head teachers and directors. Platform owner only (the
 * admin panel's sign-in). Codes already used or revoked are left out.
 */
class GiftVoucherController extends Controller
{
    public function __invoke(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('codes')))->map(fn ($id) => (int) $id)->filter()->unique();

        $codes = SubscriptionActivationCode::with('plan')
            ->whereKey($ids)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get();

        abort_if($codes->isEmpty(), 404);

        return view('vouchers.gift', [
            'codes' => $codes,
            'contact' => config('contact'),
        ]);
    }
}
