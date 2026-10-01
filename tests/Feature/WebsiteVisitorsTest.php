<?php

use App\Filament\Pages\WebsiteVisitors;
use App\Models\School;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\SiteVisits;
use App\Support\VisitorLocation;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

const PHONE_BROWSER = 'Mozilla/5.0 (Linux; Android 14; SM-A145F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';
const DESKTOP_BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RoleSeeder::class);

    // Stand-in for the MaxMind database: everyone is in Kampala.
    app()->instance(VisitorLocation::class, new class extends VisitorLocation
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function lookup(?string $ip): array
        {
            return ['country_code' => 'UG', 'country' => 'Uganda', 'city' => 'Kampala'];
        }
    });
});

it('counts a visit to a public page with where it came from, but keeps no IP address', function () {
    $this->withHeaders(['User-Agent' => PHONE_BROWSER, 'Referer' => 'https://www.google.com/'])
        ->get('/pricing')
        ->assertOk();

    $visit = SiteVisit::sole();

    expect($visit->path)->toBe('/pricing')
        ->and($visit->source)->toBe('google')
        ->and($visit->device)->toBe('mobile')
        ->and($visit->country)->toBe('Uganda')
        ->and($visit->city)->toBe('Kampala')
        ->and($visit->visitor)->toHaveLength(64)
        ->and($visit->visitor)->not->toContain('127.0.0.1')
        ->and(Schema::getColumnListing('site_visits'))->not->toContain('ip_address')
        ->and(Schema::getColumnListing('site_visits'))->not->toContain('ip');
});

it('counts the same browser once a day as a unique visitor', function () {
    $this->withHeaders(['User-Agent' => DESKTOP_BROWSER])->get('/');
    $this->withHeaders(['User-Agent' => DESKTOP_BROWSER])->get('/about');

    expect(SiteVisit::count())->toBe(2)
        ->and(SiteVisit::distinct()->count('visitor'))->toBe(1)
        ->and(SiteVisit::pluck('source')->all())->toBe(['direct', 'direct']);
});

it('does not count bots, link previews, prefetches or the platform owner', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/');
    $this->withHeaders(['User-Agent' => 'WhatsApp/2.23.20.0 A'])->get('/');
    $this->withHeaders(['User-Agent' => DESKTOP_BROWSER, 'Sec-Purpose' => 'prefetch'])->get('/');

    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));
    $this->withHeaders(['User-Agent' => DESKTOP_BROWSER])->get('/contact');

    expect(SiteVisit::count())->toBe(0);
});

it('tells where a visitor came from', function (string $referrer, ?string $tag, string $source) {
    $request = Request::create('https://www.schoolhubug.com/'.($tag ? '?utm_source='.$tag : ''), 'GET', server: ['HTTP_REFERER' => $referrer]);

    expect(app(SiteVisits::class)->source($request))->toBe($source);
})->with([
    'Google' => ['https://www.google.co.ug/', null, 'google'],
    'Bing' => ['https://www.bing.com/', null, 'search'],
    'Facebook' => ['https://m.facebook.com/', null, 'facebook'],
    'X' => ['https://t.co/abc', null, 'x'],
    'own site' => ['https://www.schoolhubug.com/features', null, 'site'],
    'campaign tag' => ['', 'whatsapp', 'whatsapp'],
    'nothing' => ['', null, 'direct'],
]);

it('shows the platform owner where visitors come from, and no one else', function () {
    foreach (['/', '/pricing', '/pricing'] as $path) {
        $this->withHeaders(['User-Agent' => PHONE_BROWSER])->get($path);
    }

    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(WebsiteVisitors::class)
        ->assertOk()
        ->assertSee(['Visits per day', 'Uganda', 'Kampala, Uganda', 'Pricing', 'Home page', 'Phone'])
        ->assertDontSee('Visitor locations are not switched on yet');

    expect((new WebsiteVisitors)->totals())->toMatchArray(['visits' => 3, 'visitors' => 1, 'countries' => 1]);

    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($school);
    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));

    expect(WebsiteVisitors::canAccess())->toBeFalse();
});

it('explains how to switch on locations until the database is there', function () {
    app()->forgetInstance(VisitorLocation::class);
    config(['services.maxmind.database' => storage_path('framework/testing/no-such.mmdb')]);
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(WebsiteVisitors::class)
        ->assertSee('Visitor locations are not switched on yet')
        ->assertSee('geoip:update');
});

it('asks for the MaxMind details before downloading', function () {
    config(['services.maxmind.account_id' => null, 'services.maxmind.license_key' => null]);

    $this->artisan('geoip:update')
        ->expectsOutputToContain('Set MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY')
        ->assertFailed();
});
