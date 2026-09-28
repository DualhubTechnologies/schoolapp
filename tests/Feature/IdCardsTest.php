<?php

use App\Filament\Pages\IdCards;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\IdCardService;
use App\Services\Subscriptions\SubscriptionManager;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'phone' => '0414 000 111', 'unique_code' => 'SH12345']);
    SubscriptionManager::startTrial($this->school);
    $this->year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-02-01', 'end_date' => '2026-12-04', 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $this->guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Okello Peter', 'phone' => '0772123456', 'relationship' => 'father']);

    $this->ready = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'guardian_id' => $this->guardian->id,
        'first_name' => 'Nakato', 'last_name' => 'Grace', 'admission_no' => 'ADM-1', 'gender' => 'female',
        'date_of_birth' => '2016-03-14', 'photo' => 'students/nakato.jpg', 'status' => 'active',
    ]);
    $this->incomplete = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $this->class->id,
        'first_name' => 'Mukasa', 'last_name' => 'John', 'admission_no' => 'ADM-2', 'status' => 'active',
    ]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('previews a class and names what each incomplete card is missing', function () {
    Livewire::test(IdCards::class)
        ->set('classId', $this->class->id)
        ->assertOk()
        ->assertSee('Nakato Grace')
        ->assertSee('Back of every card')
        ->assertSee('1 is missing details and cannot be printed yet')
        ->assertSee('Missing: photo, date of birth, sex, parent / guardian phone.');
});

it('puts the learner\'s details on the front', function () {
    $card = app(IdCardService::class)->cardData($this->ready->fresh(), IdCardTemplate::forSchool($this->school->id), CarbonImmutable::parse('2026-09-28'));

    expect($card['fields'])->toBe([
        'Student No.' => 'ADM-1',
        'Class' => 'P.4',
        'Sex' => 'Female',
        'Date of Birth' => '14/03/2016',
        'Parent Tel.' => '0772123456',
    ])
        ->and($card['cardNumber'])->toBe('SH12345/26/'.str_pad((string) $this->ready->id, 5, '0', STR_PAD_LEFT))
        ->and($card['expiresOn']->format('Y-m-d'))->toBe('2026-12-04');
});

it('uses the defaults until the school saves a template', function () {
    $template = IdCardTemplate::forSchool($this->school->id);

    expect($template->exists)->toBeFalse()
        ->and($template->orientation)->toBe('landscape')
        ->and($template->noteLines())->toHaveCount(3);
});

it('saves the school\'s template and uses it from then on', function () {
    Livewire::test(IdCards::class)
        ->callAction('template', data: [
            'orientation' => 'portrait',
            'primary_color' => '#0F6B3A',
            'accent_color' => '#f2c230',
            'validity' => 'months',
            'validity_months' => 18,
            'back_notes' => "Carry this card at all times.\nReturn it to the bursar if found.",
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Template saved');

    $template = IdCardTemplate::forSchool($this->school->id);

    expect($template->exists)->toBeTrue()
        ->and($template->only(['orientation', 'primary_color', 'accent_color', 'validity', 'validity_months']))->toBe([
            'orientation' => 'portrait', 'primary_color' => '#0f6b3a', 'accent_color' => '#f2c230', 'validity' => 'months', 'validity_months' => 18,
        ])
        ->and($template->noteLines())->toBe(['Carry this card at all times.', 'Return it to the bursar if found.'])
        ->and($template->expiresOn(CarbonImmutable::parse('2026-09-28'), $this->year)->format('Y-m-d'))->toBe('2028-03-28');

    $this->get(route('filament.app.students.id-cards.print', ['students' => $this->ready->id]))
        ->assertOk()
        ->assertSee('idc-portrait')
        ->assertSee('#0f6b3a')
        ->assertSee('Return it to the bursar if found.');
});

it('rejects a colour that is not a hex code', function () {
    Livewire::test(IdCards::class)
        ->callAction('template', data: ['orientation' => 'landscape', 'primary_color' => 'red', 'accent_color' => '#f2c230', 'validity' => 'academic_year'])
        ->assertHasActionErrors(['primary_color']);

    expect(IdCardTemplate::where('school_id', $this->school->id)->exists())->toBeFalse();
});

it('lets staff without settings access print but not edit the template', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    Livewire::test(IdCards::class)
        ->assertOk()
        ->assertActionHidden('template');
});

it('keeps text readable on any colour', function () {
    expect(IdCardTemplate::textOn('#13294b'))->toBe('#ffffff')
        ->and(IdCardTemplate::textOn('#f2c230'))->toBe('#1b2433')
        ->and(IdCardTemplate::hex('not a colour', '#13294b'))->toBe('#13294b');
});

it('refuses to print or export a batch with a card missing details', function () {
    $ids = $this->ready->id.','.$this->incomplete->id;

    $this->get(route('filament.app.students.id-cards.print', ['students' => $ids]))->assertStatus(422);
    $this->get(route('filament.app.students.id-cards.export', ['students' => $ids]))->assertStatus(422);
});

it('prints the front with the card number and dates, and the shared back', function () {
    $this->get(route('filament.app.students.id-cards.print', ['students' => $this->ready->id]))
        ->assertOk()
        ->assertSee('idc-landscape')
        ->assertSee('Nakato Grace')
        ->assertSee('SH12345/26/')
        ->assertSee('Expires')
        ->assertSee('04/12/2026')
        ->assertSee('If found, please return to')
        ->assertSee(explode("\n", IdCardTemplate::DEFAULT_BACK_NOTES)[0]);
});

it('exports complete cards as a PDF in either orientation', function (string $orientation) {
    IdCardTemplate::create(['school_id' => $this->school->id, 'orientation' => $orientation]);

    $this->get(route('filament.app.students.id-cards.export', ['students' => $this->ready->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
})->with(['landscape', 'portrait']);

it('does not print another school\'s students', function () {
    $other = School::create(['name' => 'Other', 'slug' => 'other', 'email' => 'o@example.com', 'school_type' => 'primary']);
    $stranger = Student::create(['school_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Child', 'admission_no' => 'X-1', 'status' => 'active']);

    $this->get(route('filament.app.students.id-cards.print', ['students' => $stranger->id]))->assertNotFound();
});
