<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Contracts\View\View;

/**
 * The public home page at the site root: what SchoolHub does, what it
 * costs, and the way in (sign in / register a school).
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('landing', [
            'plans' => Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort_order')->get(),
            'trialDays' => (int) config('subscriptions.trial_days', 30),
            'contact' => config('contact'),
            'learnerRanges' => \App\Models\DemoRequest::LEARNERS,
            'contactMethods' => \App\Models\DemoRequest::CONTACT_METHODS,
        ]);
    }
}
