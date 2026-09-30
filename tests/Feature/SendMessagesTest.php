<?php

use App\Filament\Pages\SendMessages;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\MessageBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\User;
use App\Services\Messaging\BulkMessages;
use App\Services\Messaging\MessageRecipient;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');
    config(['sms.driver' => 'log']);

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $this->p4 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $this->p5 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.5']);

    // Two sisters share a mother; a third learner has no phone at all.
    $mother = Guardian::create(['school_id' => $this->school->id, 'name' => 'Nakato Sarah', 'phone' => '0772123456']);
    $this->aisha = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->p4->id, 'guardian_id' => $mother->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);
    $this->amina = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->p5->id, 'guardian_id' => $mother->id, 'name' => 'Amina Nakato', 'admission_no' => 'ADM-002', 'status' => 'active']);
    $this->brian = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->p4->id, 'name' => 'Brian Ssemanda', 'admission_no' => 'ADM-003', 'status' => 'active']);

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

it('sends one SMS per family, and one per child when the message names them', function () {
    $messages = app(BulkMessages::class);

    $plain = $messages->recipients($this->school, 'families', [], 'School closes at 1pm on Friday.');
    $named = $messages->recipients($this->school, 'families', [], 'Pick up {student} at 1pm.');

    expect($plain['recipients'])->toHaveCount(1)
        ->and($plain['no_phone'])->toBe(1)
        ->and($named['recipients'])->toHaveCount(2)
        ->and($messages->render('Pick up {student} ({class}).', $this->school, $named['recipients'][0]))->toBe('Pick up Aisha Nakato (P.4).');
});

it('targets one class, or the families owing fees', function () {
    $messages = app(BulkMessages::class);

    expect($messages->students($this->school, 'class', ['class_id' => $this->p5->id])->pluck('name')->all())->toBe(['Amina Nakato'])
        ->and($messages->students($this->school, 'class', []))->toBeEmpty()
        ->and($messages->students($this->school, 'owing', []))->toBeEmpty();

    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    StudentCharge::create(['student_id' => $this->amina->id, 'school_id' => $this->school->id, 'term_id' => $term->id, 'description' => 'Tuition', 'amount' => 300000, 'discount_amount' => 0, 'charged_on' => now()->toDateString()]);

    expect($messages->students($this->school, 'owing', [])->pluck('name')->all())->toBe(['Amina Nakato'])
        ->and($messages->render('{student} owes {balance}.', $this->school, new MessageRecipient('+256772123456', 'Amina Nakato', $this->amina)))->toBe('Amina Nakato owes UGX 300,000.');
});

it('texts staff with a phone number', function () {
    Staff::create(['school_id' => $this->school->id, 'name' => 'Okello James', 'phone' => '0701222333', 'staff_no' => 'ST-1', 'position' => 'Teacher', 'employment_date' => '2020-01-06', 'status' => 'active']);
    Staff::create(['school_id' => $this->school->id, 'name' => 'No Phone', 'staff_no' => 'ST-2', 'position' => 'Cook', 'employment_date' => '2020-01-06', 'status' => 'active']);

    $staff = app(BulkMessages::class)->recipients($this->school, 'staff', [], 'Staff meeting at 4pm.');

    expect($staff['recipients']->map(fn ($r) => $r->name)->all())->toBe(['Okello James'])->and($staff['no_phone'])->toBe(1);
});

it('sends from the page in the background and records how it went', function () {
    Log::spy();
    $this->actingAs($this->admin);

    Livewire::test(SendMessages::class)
        ->set('audience', 'families')
        ->set('body', 'Dear parent, {student} has a sports day on Friday.')
        ->assertSee('Dear parent, Aisha Nakato has a sports day on Friday.')
        ->callAction('send')
        ->assertNotified('Message on its way')
        ->assertSet('body', '');

    $batch = MessageBatch::sole();

    expect($batch->status)->toBe('done')
        ->and($batch->recipients)->toBe(2)
        ->and($batch->sent)->toBe(2)
        ->and($batch->no_phone)->toBe(1)
        ->and($batch->audience_label)->toBe('Parents of all learners');

    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'SMS (log driver)' && $context['message'] === 'Dear parent, Amina Nakato has a sports day on Friday.')->once();
});

it('counts SMS parts', function () {
    expect(BulkMessages::parts(str_repeat('a', 160)))->toBe(1)
        ->and(BulkMessages::parts(str_repeat('a', 161)))->toBe(2)
        ->and(BulkMessages::parts(str_repeat('a', 307)))->toBe(3);
});

it('is only for users given the Messages module', function () {
    $teacher = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');

    $this->actingAs($teacher)->get(SendMessages::getUrl())->assertForbidden();
    $this->actingAs($this->admin)->get(SendMessages::getUrl())->assertOk();
});
