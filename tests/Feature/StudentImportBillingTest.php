<?php

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\Term;
use App\Models\User;
use App\Services\BillingService;
use App\Services\StudentCsvImporter;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'status' => 'active']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.1']);

    $fee = fn (array $data) => FeeStructure::create($data + ['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'applies_to' => 'all', 'is_active' => true]);
    $fee(['name' => 'Tuition', 'frequency' => 'per_term', 'term_id' => $this->term->id, 'amount' => 400000]);
    $fee(['name' => 'Admission', 'frequency' => 'once', 'applies_to' => 'new_only', 'amount' => 50000]);

    Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'first_name' => 'Existing', 'last_name' => 'Learner', 'admission_no' => 'ADM-1', 'status' => 'active']);

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
    $this->actingAs($this->admin);

    Storage::disk('local')->put('imports/mid-term.csv', "first_name,last_name,admission_no,class\nJoan,Nakato,ADM-2,P.1\nBrian,Okello,ADM-3,P.1\n");

    $this->runImport = function (bool $existing = false): StudentImport {
        $import = StudentImport::create(['school_id' => $this->school->id, 'imported_by' => $this->admin->id, 'file_name' => 'mid-term.csv', 'file_path' => 'imports/mid-term.csv', 'status' => 'pending', 'confirm_on_import' => $existing]);
        (new StudentCsvImporter($import))->validate();
        (new StudentCsvImporter($import->fresh()))->import();

        return $import->fresh();
    };
});

it('bills learners imported after the term was billed, admission fees included for new admissions', function () {
    app(BillingService::class)->billTerm($this->term);

    $import = ($this->runImport)();

    expect(Student::where('admission_no', 'ADM-2')->first()->balance())->toEqual(450000.0)
        ->and($import->billed_students)->toBe(2)
        ->and((float) $import->billed_amount)->toBe(900000.0)
        ->and(collect($import->import_log)->last()['message'])->toContain('2 imported learners billed UGX 900,000');
});

it('bills continuing learners imported mid-term for the term only, no admission fees', function () {
    app(BillingService::class)->billTerm($this->term);

    ($this->runImport)(existing: true);

    expect(Student::where('admission_no', 'ADM-3')->first()->balance())->toEqual(400000.0);
});

it('charges nothing on import before the term is billed: billing the term covers them', function () {
    $import = ($this->runImport)();

    expect(Student::where('admission_no', 'ADM-2')->first()->balance())->toEqual(0.0)
        ->and($import->billed_students)->toBe(0)
        ->and(app(BillingService::class)->termNeedsBilling($this->term))->toBeTrue();
});
