<?php

use App\Filament\Admin\Resources\Schools\Tables\SchoolsTable;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RegisterSchool;
use App\Filament\Pages\Auth\VerifyEmail;
use App\Models\School;
use App\Models\User;
use App\Notifications\ConfirmYourEmail;
use App\Notifications\WelcomeToSchoolHub;
use App\Support\EmailVerificationCode;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Filament::setCurrentPanel('app');
});

/**
 * Register a school through the real form and return its administrator
 * with the code that was emailed to them.
 *
 * @return array{0: User, 1: string}
 */
function registerAndGetCode(string $email = 'head@hope.test'): array
{
    Livewire::test(RegisterSchool::class)
        ->fillForm([
            'school_name' => 'Hope Primary School',
            'school_type' => School::TYPE_PRIMARY,
            'city' => 'Wakiso',
            'name' => 'Adrian Mugizi',
            'phone' => '0757490220',
            'email' => $email,
            'password' => 'Abc123',
            'passwordConfirmation' => 'Abc123',
            'terms' => true,
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', $email)->sole();

    return [$user, latestCodeFor($user)];
}

function latestCodeFor(User $user): string
{
    $code = null;

    Notification::assertSentTo($user, ConfirmYourEmail::class, function (ConfirmYourEmail $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('emails a 6-digit code to a new school administrator and holds the welcome email back', function () {
    [$user, $code] = registerAndGetCode();

    expect($code)->toMatch('/^\d{6}$/')
        ->and(EmailVerificationCode::isPending($user))->toBeTrue()
        ->and($user->email_verification_code)->not->toBe($code)
        ->and(session(EmailVerificationCode::SESSION_KEY))->toBe($user->id);

    Notification::assertNotSentTo($user, WelcomeToSchoolHub::class);
});

it('confirms the email with the right code, then sends the welcome email and the sign-in page', function () {
    [$user, $code] = registerAndGetCode();

    Livewire::test(VerifyEmail::class)
        ->assertSee('he••••@hope.test')
        ->fillForm(['code' => $code])
        ->call('verify')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getLoginUrl());

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->email_verification_code)->toBeNull()
        ->and(EmailVerificationCode::isPending($user))->toBeFalse()
        ->and(session()->has(EmailVerificationCode::SESSION_KEY))->toBeFalse()
        ->and(auth()->check())->toBeFalse();

    Notification::assertSentTo($user, WelcomeToSchoolHub::class);
});

it('rejects a wrong code and stops guessing after five tries', function () {
    [$user, $code] = registerAndGetCode();
    $wrong = $code === '111111' ? '222222' : '111111';

    $page = Livewire::test(VerifyEmail::class);

    foreach (range(1, EmailVerificationCode::MAX_ATTEMPTS) as $try) {
        $page->fillForm(['code' => $wrong])->call('verify')->assertHasFormErrors(['code']);
    }

    // Even the right code no longer works: a new one must be sent.
    $page->fillForm(['code' => $code])->call('verify')->assertHasFormErrors(['code']);

    expect(EmailVerificationCode::isPending($user->refresh()))->toBeTrue();
});

it('does not accept a code after it expires', function () {
    [$user, $code] = registerAndGetCode();

    $this->travel(EmailVerificationCode::EXPIRES_AFTER_MINUTES + 1)->minutes();

    expect(EmailVerificationCode::attempt($user->refresh(), $code))->toBe(EmailVerificationCode::EXPIRED)
        ->and(EmailVerificationCode::isPending($user))->toBeTrue();
});

it('sends a new code on request, but not more than once a minute', function () {
    [$user, $firstCode] = registerAndGetCode();

    Livewire::test(VerifyEmail::class)->callAction('resend');
    Notification::assertSentToTimes($user, ConfirmYourEmail::class, 1);

    $this->travel(EmailVerificationCode::RESEND_AFTER_SECONDS + 1)->seconds();

    Livewire::test(VerifyEmail::class)->callAction('resend');
    Notification::assertSentToTimes($user, ConfirmYourEmail::class, 2);

    $user->refresh();
    $secondCode = latestCodeFor($user);

    if ($secondCode !== $firstCode) {
        expect(EmailVerificationCode::attempt($user, $firstCode))->toBe(EmailVerificationCode::WRONG);
    }

    expect(EmailVerificationCode::attempt($user, $secondCode))->toBe(EmailVerificationCode::VERIFIED);
});

it('sends an unconfirmed administrator from sign-in to the code page without signing them in', function () {
    [$user] = registerAndGetCode();
    session()->forget(EmailVerificationCode::SESSION_KEY);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'Abc123'])
        ->call('authenticate')
        ->assertRedirect(VerifyEmail::url());

    expect(auth()->check())->toBeFalse()
        ->and(session(EmailVerificationCode::SESSION_KEY))->toBe($user->id);
});

it('does not reveal an unconfirmed account to someone with the wrong password', function () {
    [$user] = registerAndGetCode();
    session()->forget(EmailVerificationCode::SESSION_KEY);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'not-the-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->assertNoRedirect();

    expect(session()->has(EmailVerificationCode::SESSION_KEY))->toBeFalse();
});

it('signs in a confirmed administrator as usual', function () {
    [$user, $code] = registerAndGetCode();
    EmailVerificationCode::attempt($user, $code);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'Abc123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->id())->toBe($user->id);
});

it('leaves accounts that were never sent a code alone', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    expect(EmailVerificationCode::isPending($user))->toBeFalse();
});

it('sends visitors with nothing to confirm to the sign-in page', function () {
    Livewire::test(VerifyEmail::class)->assertRedirect(Filament::getLoginUrl());
});

it('lets the platform owner confirm an administrator by hand', function () {
    [$user] = registerAndGetCode();

    SchoolsTable::confirmAdminEmail()->record($user->school)->call();

    expect(EmailVerificationCode::isPending($user->refresh()))->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull();
});
