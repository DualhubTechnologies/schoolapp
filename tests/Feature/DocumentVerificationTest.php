<?php

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\DocumentVerification;
use App\Models\Mark;
use App\Models\ReportCardTemplate;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.6']);
    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);
    $english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'curriculum' => 'primary']);
    $this->class->subjects()->attach($english->id);
    $exam = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of term', 'type' => 'eot', 'max_score' => 100]);
    $this->mark = Mark::create(['assessment_id' => $exam->id, 'student_id' => $this->student->id, 'subject_id' => $english->id, 'score' => 72]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

function printCardForVerification(): TestResponse
{
    return test()->get(route('filament.app.academics.report-cards', ['term' => test()->term->id, 'class' => test()->class->id]));
}

it('prints a QR code and verification code on each report card', function () {
    printCardForVerification()->assertOk()->assertSee('Scan to verify');

    $document = DocumentVerification::sole();

    expect($document->code)->toMatch('/^RC-[A-Z0-9]{4}-[A-Z0-9]{4}$/')
        ->and($document->summary['learner'])->toBe('Aisha Nakato')
        ->and($document->summary['result']['Average'])->toBe('72%');

    printCardForVerification()->assertSee($document->code)->assertSee('data:image/svg+xml;base64,', false);
});

it('shows anyone who scans the code that the card is genuine, without logging in', function () {
    printCardForVerification();
    $code = DocumentVerification::sole()->code;

    auth()->logout();

    $this->get(route('verify.show', $code))
        ->assertOk()
        ->assertSee('Genuine report card')
        ->assertSee('Aisha Nakato')
        ->assertSee('ADM-001')
        ->assertSee('72%');

    // A typed code, lower case and without dashes, finds the same page.
    $this->get(route('verify.form', ['code' => strtolower(str_replace('-', ' ', $code))]))
        ->assertRedirect(route('verify.show', $code));
});

it('keeps the code when a card is reprinted unchanged, and replaces it when the result changes', function () {
    printCardForVerification();
    printCardForVerification();
    $first = DocumentVerification::sole();

    $this->mark->update(['score' => 85]);
    printCardForVerification();

    $first->refresh();
    $current = DocumentVerification::whereNull('replaced_at')->sole();

    expect($first->replaced_at)->not->toBeNull()
        ->and($current->code)->not->toBe($first->code)
        ->and($current->summary['result']['Average'])->toBe('85%');

    $this->get(route('verify.show', $first->code))->assertOk()->assertSee('since updated');
});

it('says when a code was never issued', function () {
    $this->get(route('verify.show', 'RC-AAAA-BBBB'))
        ->assertOk()
        ->assertSee('No document with this code');
});

it('leaves the QR code off when the school turns it off', function () {
    ReportCardTemplate::create(['school_id' => $this->school->id, ...ReportCardTemplate::DEFAULTS, 'show' => ['verification' => false]]);

    printCardForVerification()->assertOk()->assertDontSee('Scan to verify');

    expect(DocumentVerification::count())->toBe(0);
});
