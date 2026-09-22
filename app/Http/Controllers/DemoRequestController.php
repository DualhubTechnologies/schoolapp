<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use App\Notifications\DemoRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * The "Book a demo" form on the landing page. The request is saved first,
 * so it is never lost if the email to the team fails.
 */
class DemoRequestController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Honeypot: people never see this field, bots fill it in.
        if (filled($request->input('website'))) {
            return redirect()->to(url('/') . '#demo')->with('demo_sent', true);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'school_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^[\d\s\+\-\(\)]{9,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'learners' => ['nullable', 'in:' . implode(',', array_keys(DemoRequest::LEARNERS))],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_contact' => ['required', 'in:' . implode(',', array_keys(DemoRequest::CONTACT_METHODS))],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.regex' => 'Enter a valid phone number.',
            'preferred_date.after_or_equal' => 'Choose today or a later date.',
        ], [
            'school_name' => 'school name',
            'preferred_date' => 'preferred date',
        ]);

        // Back to the form itself, not the top of the page.
        if ($validator->fails()) {
            return redirect()->to(url('/') . '#demo')->withErrors($validator, 'demo')->withInput();
        }

        $data = $validator->validated();

        $demo = DemoRequest::create($data + ['ip_address' => $request->ip()]);

        try {
            Notification::route('mail', config('contact.email'))->notify(new DemoRequested($demo));
        } catch (\Throwable $e) {
            report($e);   // saved already; it shows in Platform Management → Demo requests
        }

        return redirect()->to(url('/') . '#demo')->with('demo_sent', $demo->name);
    }
}
