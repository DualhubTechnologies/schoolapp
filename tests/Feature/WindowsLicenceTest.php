<?php

use App\Filament\Admin\Resources\WindowsLicences\Pages\ManageWindowsLicences;
use App\Filament\Pages\Licence;
use App\Models\IssuedLicence;
use App\Models\LicenceKeyRecord;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Licensing\DesktopLicence;
use App\Support\Licensing\LicenceIssuer;
use App\Support\Licensing\LicenceKey;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RoleSeeder::class);

    // A key pair for these tests only.
    $pair = sodium_crypto_sign_keypair();
    config([
        'licence.private_key' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        'licence.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
    ]);

    $this->standard = Plan::where('name', 'Standard')->sole();
});

function desktopSchool(): School
{
    config(['app.edition' => 'desktop']);

    return School::create([
        'name' => 'Light Secondary School', 'slug' => 'light', 'unique_code' => 'SH48213',
        'email' => 'light@example.com', 'school_type' => 'secondary', 'status' => 'active',
    ]);
}

function issueFor(School $school, string $starts, string $ends, array $extra = []): IssuedLicence
{
    config(['app.edition' => 'server']);
    $licence = app(LicenceIssuer::class)->issue([
        'school_name' => $school->name,
        'school_code' => $school->unique_code,
        'plan_id' => test()->standard->id,
        'max_students' => 800,
        'max_users' => 25,
        'cycle' => 'term',
        'starts_on' => $starts,
        'ends_on' => $ends,
        'amount' => 330000,
        ...$extra,
    ]);
    config(['app.edition' => 'desktop']);

    return $licence;
}

it('signs a key that only checks out unchanged, and with the right public key', function () {
    $details = ['id' => 'L-2026-0001', 'school' => 'Light Secondary School', 'code' => 'SH48213', 'plan' => 'Standard', 'students' => 800, 'users' => 25, 'cycle' => 'term', 'starts' => '2026-10-01', 'ends' => '2027-01-31', 'issued' => '2026-10-01T09:00:00+03:00'];
    $key = LicenceKey::sign($details, config('licence.private_key'));

    expect($key)->toStartWith('SHL1.')
        ->and(LicenceKey::verify($key, config('licence.public_key'))?->details)->toBe($details)
        // Spaces and line breaks from WhatsApp do not matter.
        ->and(LicenceKey::verify(chunk_split($key, 40, "\n "), config('licence.public_key')))->not->toBeNull();

    // Change the end date inside the key: the signature no longer matches.
    [$prefix, $body, $signature] = explode('.', $key);
    $tampered = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
    $tampered['ends'] = '2030-12-31';
    $forged = $prefix.'.'.rtrim(strtr(base64_encode(json_encode($tampered)), '+/', '-_'), '=').'.'.$signature;

    $other = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));

    expect(LicenceKey::verify($forged, config('licence.public_key')))->toBeNull()
        ->and(LicenceKey::verify($key, $other))->toBeNull()
        ->and(LicenceKey::verify('SHL1.nonsense', config('licence.public_key')))->toBeNull();
});

it('runs on the free trial until a key is entered', function () {
    $school = desktopSchool();

    $status = SubscriptionManager::status($school);

    expect($status['state'])->toBe('trial')
        ->and($status['ends_on']->toDateString())->toBe(today()->addDays(29)->toDateString());
});

it('unlocks with the school\'s own key and takes its plan limits', function () {
    $school = desktopSchool();
    $issued = issueFor($school, today()->toDateString(), today()->addMonths(4)->toDateString(), ['max_students' => 2]);

    DesktopLicence::activate($school, $issued->key);
    $status = SubscriptionManager::status($school);

    expect($status['state'])->toBe('active')
        ->and($status['plan']->name)->toBe('Standard')
        ->and($status['ends_on']->toDateString())->toBe(today()->addMonths(4)->toDateString())
        ->and(SubscriptionManager::roomForStudents($school))->toBe(2);

    $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'S.1']);
    foreach ([1, 2] as $n) {
        Student::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'first_name' => 'Learner', 'last_name' => (string) $n, 'admission_no' => "A-{$n}", 'status' => 'active']);
    }

    expect(SubscriptionManager::roomForStudents($school))->toBe(0);
});

it('refuses a key for another school, a changed key, or the same key twice', function () {
    $school = desktopSchool();
    $other = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'unique_code' => 'SH11111', 'email' => 'hope@example.com', 'school_type' => 'primary', 'status' => 'active']);
    $theirs = issueFor($other, today()->toDateString(), today()->addMonths(4)->toDateString());
    $ours = issueFor($school, today()->toDateString(), today()->addMonths(4)->toDateString());

    expect(fn () => DesktopLicence::activate($school, $theirs->key))->toThrow(RuntimeException::class, 'not this school')
        ->and(fn () => DesktopLicence::activate($school, substr($ours->key, 0, -3).'abc'))->toThrow(RuntimeException::class, 'not valid');

    DesktopLicence::activate($school, $ours->key);

    expect(fn () => DesktopLicence::activate($school, $ours->key))->toThrow(RuntimeException::class, 'already been entered');
});

it('extends with a key paid ahead, then gives grace days, then locks', function () {
    $school = desktopSchool();
    DesktopLicence::activate($school, issueFor($school, today()->subMonths(4)->toDateString(), today()->subDays(3)->toDateString())->key);

    expect(SubscriptionManager::status($school)['state'])->toBe('grace');

    $this->travel(20)->days();
    expect(SubscriptionManager::status($school)['state'])->toBe('expired')
        ->and(SubscriptionManager::isLocked($school))->toBeTrue();

    // Renewed (time only moves forward: going back would look like a clock set back).
    DesktopLicence::activate($school, issueFor($school, today()->toDateString(), today()->addMonths(4)->toDateString())->key);

    expect(SubscriptionManager::status($school)['state'])->toBe('active');
});

it('ignores dates edited in the database: only the signed key counts', function () {
    $school = desktopSchool();
    DesktopLicence::activate($school, issueFor($school, today()->subMonths(5)->toDateString(), today()->subMonth()->toDateString())->key);

    LicenceKeyRecord::query()->update(['ends_on' => '2035-12-31']);
    DB::table('subscriptions')->update(['ends_on' => '2035-12-31']);

    expect(SubscriptionManager::status($school)['state'])->toBe('expired');
});

it('locks when the computer\'s clock is set back', function () {
    $school = desktopSchool();
    DesktopLicence::activate($school, issueFor($school, today()->subMonth()->toDateString(), today()->addMonths(3)->toDateString())->key);

    expect(SubscriptionManager::status($school)['state'])->toBe('active');

    $this->travel(-3)->days();

    expect(SubscriptionManager::status($school)['state'])->toBe('clock')
        ->and(SubscriptionManager::isLocked($school))->toBeTrue();

    $this->travelBack();
    expect(SubscriptionManager::status($school)['state'])->toBe('active');
});

it('brings a locked school to its Licence page, where the administrator enters the key', function () {
    $school = desktopSchool();
    $admin = User::factory()->create(['school_id' => $school->id])->assignRole('School Admin');
    $this->travel(60)->days();
    Filament::setCurrentPanel('app');
    $this->actingAs($admin);

    expect(SubscriptionManager::isLocked($school))->toBeTrue();
    $this->get('/dashboard')->assertRedirect(Licence::getUrl());

    $issued = issueFor($school, today()->toDateString(), today()->addMonths(4)->toDateString());

    Livewire::test(Licence::class)
        ->assertSee('SH48213')
        ->assertSee('Light Secondary School')
        ->set('key', 'SHL1.wrong')
        ->call('activate')
        ->assertHasErrors('key')
        ->set('key', $issued->key)
        ->call('activate')
        ->assertHasNoErrors()
        ->assertNotified('Licence entered');

    expect(SubscriptionManager::isLocked($school))->toBeFalse();
});

it('issues numbered licences on the server and refuses without a signing key', function () {
    $school = desktopSchool();
    $first = issueFor($school, '2026-10-01', '2027-01-31');
    $second = issueFor($school, '2027-02-01', '2027-05-31');

    expect($first->licence_no)->toBe('L-'.now()->format('Y').'-0001')
        ->and($second->licence_no)->toBe('L-'.now()->format('Y').'-0002')
        ->and(LicenceKey::verify($first->key, config('licence.public_key'))?->details['code'])->toBe('SH48213')
        ->and(LicenceIssuer::defaultEnd(CarbonImmutable::parse('2026-10-01'), 'year')->toDateString())->toBe('2027-09-30');

    config(['licence.private_key' => null]);
    expect(fn () => issueFor($school, '2026-10-01', '2027-01-31'))->toThrow(RuntimeException::class, 'licence:keygen');
});

it('creates the key pair once, into .env, and will not replace it by accident', function () {
    $dir = storage_path('framework/testing/licence-env');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/.env', "APP_NAME=SchoolHub\n");
    $previous = app()->environmentPath();
    app()->useEnvironmentPath($dir);
    config(['licence.private_key' => null]);

    $this->artisan('licence:keygen')->expectsOutputToContain('Public key')->assertSuccessful();

    $env = File::get($dir.'/.env');
    preg_match('/^LICENCE_PRIVATE_KEY=(.+)$/m', $env, $private);
    preg_match('/^LICENCE_PUBLIC_KEY=(.+)$/m', $env, $public);

    $details = ['id' => 'L-1', 'school' => 'A', 'code' => 'B', 'plan' => 'P', 'students' => null, 'users' => null, 'cycle' => 'term', 'starts' => '2026-01-01', 'ends' => '2026-04-30', 'issued' => '2026-01-01'];
    expect(LicenceKey::verify(LicenceKey::sign($details, $private[1]), $public[1]))->not->toBeNull();

    config(['licence.private_key' => $private[1]]);
    $this->artisan('licence:keygen')->assertFailed();

    app()->useEnvironmentPath($previous);
    File::deleteDirectory($dir);
});

it('lets the platform owner issue a licence from the Windows licences page', function () {
    config(['app.edition' => 'server']);
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(ManageWindowsLicences::class)
        ->callAction('issue', data: [
            'school_name' => 'Light Secondary School',
            'school_code' => 'sh48213',
            'plan_id' => $this->standard->id,
            'cycle' => 'year',
            'max_students' => 800,
            'max_users' => 25,
            'starts_on' => '2026-10-01',
            'ends_on' => '2027-09-30',
            'amount' => 900000,
            'payment_reference' => 'MTN 123456',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $issued = IssuedLicence::sole();

    expect($issued->school_code)->toBe('SH48213')
        ->and($issued->cycle)->toBe('year')
        ->and(LicenceKey::verify($issued->key, config('licence.public_key'))?->details['ends'])->toBe('2027-09-30');
});
