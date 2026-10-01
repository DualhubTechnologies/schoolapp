<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireDesktopSetup;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Edition;
use App\Support\PasswordStrength;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * First run of the Windows app: the school and its administrator, in one
 * short form, with no email code or approval (there is no internet to
 * wait on, and nobody else to approve it). Once a school exists the page
 * is gone; there is only ever one school in the app.
 */
class DesktopSetupController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if ($redirect = $this->unavailable()) {
            return $redirect;
        }

        return view('desktop.setup', ['types' => School::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->unavailable()) {
            return $redirect;
        }

        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'school_type' => ['required', 'in:'.implode(',', array_keys(School::TYPES))],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[\d\s\+\-\(\)]{9,20}$/'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordStrength::rule()],
        ], [
            'phone.regex' => 'Enter a valid phone number.',
        ], [
            'school_name' => 'school name',
            'school_type' => 'school type',
            'city' => 'town or district',
        ]);

        $user = DB::transaction(function () use ($data): User {
            if (! Role::query()->exists()) {
                (new RoleSeeder)->run();
            }

            // Creating the school also seeds its class levels (SchoolObserver).
            $school = School::create([
                'name' => $data['school_name'],
                'slug' => Str::slug($data['school_name']) ?: 'school',
                'unique_code' => 'SH'.random_int(10000, 99999),
                'school_type' => $data['school_type'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'country' => 'Uganda',
                'currency' => 'UGX',
                'timezone' => 'Africa/Kampala',
                'contact_person' => $data['name'],
                'status' => 'active',
            ]);

            // Until the licence is entered (phase 3), the school runs on the
            // usual free trial.
            SubscriptionManager::startTrial($school);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'school_id' => $school->getKey(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('School Admin');

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to(filament()->getPanel('app')->getUrl());
    }

    /** Only in the Windows app, and only before the school exists. */
    protected function unavailable(): ?RedirectResponse
    {
        abort_unless(Edition::isDesktop(), 404);

        return RequireDesktopSetup::isSetUp()
            ? redirect()->to(filament()->getPanel('app')->getLoginUrl())
            : null;
    }
}
