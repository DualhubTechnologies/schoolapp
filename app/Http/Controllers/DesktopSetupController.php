<?php

namespace App\Http\Controllers;

use App\Filament\Pages\OnlineSync;
use App\Http\Middleware\RequireDesktopSetup;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Services\Sync\SyncClient;
use App\Services\Sync\SyncServer;
use App\Support\Edition;
use App\Support\PasswordStrength;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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

        return view('desktop.setup', ['types' => School::TYPES, 'mode' => 'new']);
    }

    /** For a school already on SchoolHub online: its account comes to this computer. */
    public function showConnect(): View|RedirectResponse
    {
        if ($redirect = $this->unavailable()) {
            return $redirect;
        }

        return view('desktop.setup', ['types' => School::TYPES, 'mode' => 'online']);
    }

    /**
     * Sets the school up from its online account: the administrator's
     * online email and password and a link code (from Settings → Windows
     * app online, or from SchoolHub). The school gets the same details and
     * licence code as online, the administrator the same sign-in, and the
     * first sync brings everything else (SyncClient), with the licence
     * from the online subscription.
     */
    public function connect(Request $request, SyncClient $client): RedirectResponse
    {
        if ($redirect = $this->unavailable()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['code' => 'link code']);

        try {
            $online = $client->connect($data['code'], $data['email'], $data['password']);
        } catch (ConnectionException) {
            return back()->withInput($request->except('password'))->withErrors(['code' => 'No internet connection. Connect this computer (phone data is enough) and try again.']);
        } catch (RuntimeException $e) {
            return back()->withInput($request->except('password'))->withErrors(['code' => $e->getMessage()]);
        }

        $user = DB::transaction(function () use ($online, $data): User {
            (new RoleSeeder)->run();

            $details = Arr::only((array) $online['school'], SyncServer::SCHOOL_DETAILS);
            $school = School::create([
                ...$details,
                'slug' => Str::slug((string) ($details['name'] ?? 'school')) ?: 'school',
                'unique_code' => $details['unique_code'] ?? 'SH'.random_int(10000, 99999),
                'country' => $details['country'] ?? 'Uganda',
                'currency' => $details['currency'] ?? 'UGX',
                'timezone' => $details['timezone'] ?? 'Africa/Kampala',
                'status' => 'active',
            ]);
            SubscriptionManager::startTrial($school);

            $user = User::create([
                'name' => $online['user']['name'],
                'email' => $online['user']['email'],
                'password' => $data['password'],
                'school_id' => $school->getKey(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('School Admin');

            return $user;
        });

        // The first sync: everything from online, and the licence. A big
        // school may take a while; if it stops, the next sync carries on.
        set_time_limit(0);

        try {
            $client->run();
        } catch (ConnectionException|RuntimeException) {
            // Picked up again by Sync now or the sync every few minutes.
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to(OnlineSync::getUrl(panel: 'app'));
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
            // Adds whichever standard roles are missing (a migration may
            // already have created some, e.g. Bursar).
            (new RoleSeeder)->run();

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
