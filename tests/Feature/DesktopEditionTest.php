<?php

use App\Filament\Pages\Backups;
use App\Filament\Pages\Licence;
use App\Filament\Pages\SpeedCheck;
use App\Models\School;
use App\Models\SmsOutbox;
use App\Models\User;
use App\Providers\Filament\AppPanelProvider;
use App\Services\SmsSender;
use App\Support\DesktopUpdates;
use App\Support\PageTimings;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    config(['app.edition' => 'desktop', 'app.url' => 'http://localhost']);
    SmsSender::resetOffline();
});

afterEach(fn () => SmsSender::resetOffline());

function setUpSchool(): User
{
    test()->post(route('desktop.setup.store'), [
        'school_name' => 'Light Secondary School',
        'school_type' => 'secondary',
        'city' => 'Wakiso',
        'phone' => '0772123456',
        'name' => 'Arinda Moreen',
        'email' => 'arinda@example.com',
        'password' => 'Kampala-2026!',
        'password_confirmation' => 'Kampala-2026!',
    ])->assertRedirect();

    return User::where('email', 'arinda@example.com')->sole();
}

it('sends every page to the first-run setup until the school exists', function () {
    $this->get('/')->assertRedirect(route('desktop.setup'));
    $this->get('/login')->assertRedirect(route('desktop.setup'));
    $this->get('/setup')->assertOk()->assertSee('Welcome to SchoolHub')->assertSee('Set up my school');
});

it('sets up the school and its administrator, signed in and ready', function () {
    $user = setUpSchool();
    $school = School::sole();

    expect($school->name)->toBe('Light Secondary School')
        ->and($school->status)->toBe('active')
        ->and($user->school_id)->toBe($school->id)
        ->and($user->hasRole('School Admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);

    // Only ever one school: the setup is gone once it exists.
    $this->post('/logout');
    $this->get('/setup')->assertRedirect();
    expect(School::count())->toBe(1);
});

it('checks the setup form like registration does', function () {
    $this->post(route('desktop.setup.store'), ['school_name' => '', 'password' => 'short', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors(['school_name', 'school_type', 'city', 'phone', 'name', 'email', 'password']);

    expect(School::count())->toBe(0);
});

it('has no public website, sitemap, parent links or owner panel', function () {
    setUpSchool();

    $this->get('/')->assertRedirect();
    $this->get('/pricing')->assertNotFound();
    $this->get('/sitemap.xml')->assertNotFound();
    $this->get('/robots.txt')->assertNotFound();
    $this->get('/p/anything')->assertNotFound();
    $this->get('/admin/login')->assertNotFound();
});

it('has no school registration', function () {
    $panel = (new AppPanelProvider(app()))->panel(Panel::make());

    expect($panel->hasRegistration())->toBeFalse();

    config(['app.edition' => 'server']);
    expect((new AppPanelProvider(app()))->panel(Panel::make())->hasRegistration())->toBeTrue();
});

it('shows a sign-in page without the website\'s links or email password resets', function () {
    setUpSchool();
    $this->post('/logout');

    expect((new AppPanelProvider(app()))->panel(Panel::make())->hasPasswordReset())->toBeFalse();

    $this->get('/login')->assertOk()
        ->assertSee('Ask the school administrator to set a new one')
        ->assertDontSee('Back to home')
        ->assertDontSee('poster-free-trial.jpg')
        ->assertDontSee('Parents:');
});

it('keeps the setup page out of the online edition', function () {
    config(['app.edition' => 'server']);

    $this->get('/setup')->assertNotFound();
});

it('keeps texts in the outbox while offline and sends them once online', function () {
    config(['sms.driver' => 'africastalking', 'sms.africastalking.api_key' => 'test-key']);

    // One fake connection with an on/off switch: fakes added later would
    // sit behind this one and never be reached.
    $tries = 0;
    $online = false;
    Http::fake(function () use (&$tries, &$online) {
        $tries++;

        if (! $online) {
            throw new ConnectionException('Could not resolve host');
        }

        return Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'messageId' => 'ATX-1']]]]);
    });

    $sms = app(SmsSender::class);
    $first = $sms->send('0772111222', 'Fees reminder 1');
    $second = $sms->send('0772333444', 'Fees reminder 2');

    expect($first)->toBe(['ok' => true, 'ref' => 'queued', 'error' => null])
        ->and($second['ref'])->toBe('queued')
        ->and(SmsOutbox::query()->waiting()->count())->toBe(2);

    // Only the first text waited for the network; the second went straight to the outbox.
    expect($tries)->toBe(1);

    // Still offline: nothing changes.
    $this->artisan('sms:send-queued')->expectsOutputToContain('Still offline')->assertSuccessful();
    expect(SmsOutbox::query()->waiting()->count())->toBe(2);

    // Back online.
    $online = true;
    $this->artisan('sms:send-queued')->expectsOutputToContain('Sent 2')->assertSuccessful();

    expect(SmsOutbox::query()->waiting()->count())->toBe(0)
        ->and(SmsOutbox::whereNotNull('sent_at')->count())->toBe(2);
});

it('gives up on a text after repeated real failures', function () {
    config(['sms.driver' => 'africastalking', 'sms.africastalking.api_key' => 'test-key']);
    SmsOutbox::create(['to' => '+256772111222', 'message' => 'Hello']);
    Http::fake(['*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 403, 'status' => 'InvalidPhoneNumber']]]])]);

    foreach (range(1, SmsOutbox::MAX_ATTEMPTS) as $run) {
        $this->artisan('sms:send-queued')->assertSuccessful();
    }

    $text = SmsOutbox::sole();
    expect($text->failed_at)->not->toBeNull()
        ->and($text->attempts)->toBe(SmsOutbox::MAX_ATTEMPTS)
        ->and($text->last_error)->toBe('InvalidPhoneNumber');
});

it('sends texts straight away online, with no outbox', function () {
    config(['app.edition' => 'server', 'sms.driver' => 'africastalking', 'sms.africastalking.api_key' => 'test-key']);
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    expect(app(SmsSender::class)->send('0772111222', 'Hi')['ok'])->toBeFalse()
        ->and(SmsOutbox::count())->toBe(0);
});

it('lets the school make and download backups, only in the Windows app', function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');
    $admin = setUpSchool();
    $this->actingAs($admin);

    expect(Backups::canAccess())->toBeTrue();
    Livewire::test(Backups::class)->assertOk()->assertSee('Keep a copy away from this computer');

    config(['app.edition' => 'server']);
    expect(Backups::canAccess())->toBeFalse();
});

it('tells the online setup tab straight away whether online SchoolHub can be reached', function () {
    Http::fake(['*/up' => Http::response('ok')]);
    $this->getJson(route('desktop.setup.connect.check'))->assertOk()->assertJson(['online' => true]);

    Http::fake(fn () => throw new ConnectionException('offline'));
    $this->getJson(route('desktop.setup.connect.check'))->assertOk()->assertJson(['online' => false]);

    $this->get(route('desktop.setup.connect'))->assertOk()->assertSee('Checking the internet connection');
});

it('does not warn "You are offline" in the Windows app, which works offline', function () {
    setUpSchool();

    $this->followingRedirects()->get('/')->assertOk()->assertSee('dataset.edition = "desktop"', false);

    config(['app.edition' => 'server']);
    $this->followingRedirects()->get('/login')->assertDontSee('dataset.edition = "desktop"', false);
});

it('offers a new version downloaded in the background, in the top bar', function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');
    $this->actingAs(setUpSchool());
    config(['nativephp.version' => '1.3.6']);

    // Nothing downloaded yet.
    $this->followingRedirects()->get('/')->assertDontSee('Update ready');

    // NativePHP's updater says a new version is downloaded.
    DesktopUpdates::listen();
    event('Native\Desktop\Events\AutoUpdater\UpdateDownloaded', [(object) ['version' => '1.3.7']]);

    expect(DesktopUpdates::ready())->toBe('1.3.7');
    $this->followingRedirects()->get('/')->assertSee('Update ready · Restart')->assertSee(route('desktop.update.install'), false);
    $this->post(route('desktop.update.install'))->assertRedirect();

    // Once this computer runs it, there is nothing left to offer.
    config(['nativephp.version' => '1.3.7']);
    expect(DesktopUpdates::ready())->toBeNull();
    $this->post(route('desktop.update.install'))->assertNotFound();
});

it('measures how long pages take on this computer, to send to SchoolHub', function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');
    PageTimings::clear();
    $this->actingAs(setUpSchool());

    $this->get(Licence::getUrl())->assertOk();
    $this->get(Licence::getUrl())->assertOk();

    $rows = collect(PageTimings::slowest());
    expect($rows->firstWhere('page', '/licence'))->toMatchArray(['count' => 2]);

    Livewire::test(SpeedCheck::class)
        ->assertSee('/licence')
        ->assertSee('Send to SchoolHub on WhatsApp')
        ->call('clear');

    expect(PageTimings::slowest())->toBe([]);

    // Not online.
    config(['app.edition' => 'server']);
    expect(SpeedCheck::canAccess())->toBeFalse();
});
