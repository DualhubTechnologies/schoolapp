<?php

use App\Filament\App\Resources\Guardians\Pages\ListGuardians;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Pages\Auth\Login;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\User;
use App\Services\ParentLogins;
use App\Services\SmsSender;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');
    config(['sms.driver' => 'log']);

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1']);

    $this->guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Akello Rose', 'phone' => '0772 555 101', 'relationship' => 'mother']);
    $this->brenda = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'guardian_id' => $this->guardian->id, 'name' => 'Brenda Ainembabazi', 'admission_no' => '003', 'status' => 'active']);
    $this->other = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Someone Else', 'admission_no' => '004', 'status' => 'active']);
    StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $this->brenda->id, 'description' => 'Tuition', 'amount' => 520000, 'charged_on' => today()]);
});

it('lets the admissions office give a parent a login, texting the PIN', function () {
    $sms = $this->mock(SmsSender::class);
    $sms->shouldReceive('send')->once()
        ->withArgs(fn ($phone, $message) => $phone === '+256772555101' && preg_match('/PIN \d{6}/', $message) === 1)
        ->andReturn(['ok' => true, 'ref' => 'x', 'error' => null]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Admissions'));

    Livewire::test(ListGuardians::class)
        ->callTableAction('giveLogin', $this->guardian, data: ['text' => true])
        ->assertNotified();

    $parent = $this->guardian->fresh()->user;

    expect($parent)->not->toBeNull()
        ->and($parent->hasRole('Parent'))->toBeTrue()
        ->and($parent->phone)->toBe('+256772555101')
        ->and($parent->school_id)->toBe($this->school->id);
});

it('lets a parent sign in with their phone number and PIN', function () {
    $pin = app(ParentLogins::class)->createFor($this->guardian, text: false)['pin'];

    Livewire::test(Login::class)
        ->fillForm(['email' => '0772 555 101', 'password' => $pin])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->id())->toBe($this->guardian->fresh()->user_id);
});

it('shows a parent only their own children, and no staff pages', function () {
    app(ParentLogins::class)->createFor($this->guardian, text: false);
    $this->actingAs($this->guardian->fresh()->user);

    $this->get(route('filament.app.pages.dashboard'))->assertOk()
        ->assertSee('Brenda Ainembabazi')
        ->assertSee('UGX 520,000')
        ->assertDontSee('Someone Else');

    $this->get(StudentResource::getUrl())->assertForbidden();
});

it('gives a parent recorded twice one login', function () {
    $first = app(ParentLogins::class)->createFor($this->guardian, text: false);
    $again = Guardian::create(['school_id' => $this->school->id, 'name' => 'Akello Rose', 'phone' => '+256 772 555101', 'relationship' => 'mother']);

    $second = app(ParentLogins::class)->createFor($again, text: false);

    expect($second['user']->is($first['user']))->toBeTrue()
        ->and($second['pin'])->toBeNull()
        ->and(User::where('phone', '+256772555101')->count())->toBe(1);
});

it('will not make a login without a usable phone number', function () {
    $this->guardian->update(['phone' => '12']);

    app(ParentLogins::class)->createFor($this->guardian, text: false);
})->throws(InvalidArgumentException::class);
