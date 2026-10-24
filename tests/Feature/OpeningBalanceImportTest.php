<?php

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\FeeBalances\Pages\ListFeeBalances;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\TransportRoute;
use App\Models\User;
use App\Services\BillingService;
use App\Services\OpeningBalanceCsvImporter;
use App\Services\StudentLedger;
use App\Services\Subscriptions\SubscriptionManager;
use App\Services\Transport\TransportLedger;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2', 'sequence' => 2]);
    $this->term3 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1']);
    $east = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'East']);

    FeeStructure::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'term_id' => $this->term3->id, 'name' => 'Tuition', 'frequency' => 'per_term', 'applies_to' => 'all', 'amount' => 500000, 'is_active' => true]);

    $learner = fn (string $name, string $admissionNo): Student => Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'section_id' => $east->id, 'name' => $name, 'admission_no' => $admissionNo, 'status' => 'active']);
    $this->sarah = $learner('Sarah Nakato', 'ADM-001');
    $this->brian = $learner('Brian Okello', 'ADM-002');
    $this->faith = $learner('Faith Achieng', 'ADM-003');

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'name' => 'Grace Bursar'])->assignRole('School Admin'));
});

/** Write CSV lines to the local disk and return the stored path. */
function openingBalancesCsv(array $rows): string
{
    $lines = array_map(fn (array $r): string => implode(',', array_map(fn ($v) => str_contains((string) $v, ',') ? '"'.$v.'"' : $v, $r)), $rows);
    $file = 'imports/opening-balances-test-'.uniqid().'.csv';
    Storage::disk('local')->put($file, implode("\n", $lines)."\n");

    return $file;
}

it('brings over what each learner owes, and the credit of a learner who paid ahead', function () {
    $file = openingBalancesCsv([
        ['admission_no', 'name', 'balance'],
        ['ADM-001', 'Sarah Nakato', '450,000/='],
        ['adm-002', 'Brian Okello', '(50,000)'],
        ['ADM-003', 'Faith Achieng', ''],
    ]);
    $importer = new OpeningBalanceCsvImporter($this->school->id);

    $check = $importer->validate($file, $this->term3, false);

    expect($check['valid'])->toBeTrue()
        ->and($check['valid_rows'])->toBe(2)
        ->and($check['blank_rows'])->toBe(1)
        ->and($check['total_owed'])->toBe(450000.0)
        ->and($check['total_credit'])->toBe(50000.0)
        ->and($check['preview'][0])->toBe(['admission_no' => 'ADM-001', 'learner' => 'Sarah Nakato', 'class' => 'S.1 East', 'balance' => '450,000 owed']);

    $result = $importer->import($file, $this->term3, false);

    expect($result)->toMatchArray(['added' => 2, 'replaced' => 0, 'removed' => 0, 'skipped' => 0])
        ->and($this->sarah->balance())->toBe(450000.0)
        ->and($this->brian->balance())->toBe(-50000.0)
        ->and($this->faith->balance())->toBe(0.0);

    $owed = StudentCharge::where('student_id', $this->sarah->id)->sole();

    expect($owed->is_opening_balance)->toBeTrue()
        ->and($owed->includes_term_fees)->toBeFalse()
        ->and($owed->term_id)->toBe($this->term3->id)
        ->and($owed->description)->toBe('Opening balance (from previous system)')
        ->and($owed->created_by)->toBe('Grace Bursar')
        ->and(StudentCharge::where('student_id', $this->brian->id)->value('description'))->toBe('Opening credit (from previous system)');
});

it('replaces a learner\'s opening balance when a corrected file is uploaded, and removes it at 0', function () {
    $importer = new OpeningBalanceCsvImporter($this->school->id);
    $importer->import(openingBalancesCsv([['admission_no', 'balance'], ['ADM-001', '450000']]), $this->term3, false);

    $corrected = openingBalancesCsv([['admission_no', 'balance'], ['ADM-001', '300000']]);

    expect($importer->validate($corrected, $this->term3, false)['replacing_rows'])->toBe(1)
        ->and($importer->import($corrected, $this->term3, false))->toMatchArray(['added' => 0, 'replaced' => 1])
        ->and($this->sarah->balance())->toBe(300000.0)
        ->and(StudentCharge::where('student_id', $this->sarah->id)->count())->toBe(1);

    $result = $importer->import(openingBalancesCsv([['admission_no', 'balance'], ['ADM-001', '0']]), $this->term3, false);

    expect($result['removed'])->toBe(1)
        ->and(StudentCharge::where('student_id', $this->sarah->id)->exists())->toBeFalse();
});

it('names each row that needs fixing and saves nothing from it', function () {
    $file = openingBalancesCsv([
        ['admission_no', 'balance'],
        ['ADM-001', '450000'],
        ['ADM-404', '10000'],
        ['ADM-002', 'lots'],
        ['ADM-003', '20000'],
        ['ADM-003', '30000'],
        ['', '5000'],
        ['ADM-002', '2000000000'],
    ]);
    $importer = new OpeningBalanceCsvImporter($this->school->id);

    $check = $importer->validate($file, $this->term3, false);
    $messages = collect($check['errors'])->pluck('message')->implode(' | ');

    expect($check['valid'])->toBeFalse()
        ->and($check['valid_rows'])->toBe(1)
        ->and($check['invalid_rows'])->toBe(6)
        ->and($messages)->toContain("No learner has admission number 'ADM-404'")
        ->and($messages)->toContain("Balance 'lots' is not an amount")
        ->and($messages)->toContain("Admission number 'ADM-003' has a balance 2 times in the file")
        ->and($messages)->toContain('Admission number is required')
        ->and($messages)->toContain('Balance must be under 1,000,000,000 shillings');

    $result = $importer->import($file, $this->term3, false);

    expect($result['added'])->toBe(1)
        ->and($result['skipped'])->toBe(6)
        ->and(StudentCharge::pluck('student_id')->all())->toBe([$this->sarah->id]);
});

it('does not match another school\'s learner by admission number', function () {
    $other = School::create(['name' => 'Other School', 'slug' => 'other', 'email' => 'other@example.com', 'school_type' => 'primary']);
    $outsider = Student::create(['school_id' => $other->id, 'name' => 'Someone Else', 'admission_no' => 'OTH-1', 'status' => 'active']);

    $result = (new OpeningBalanceCsvImporter($this->school->id))
        ->import(openingBalancesCsv([['admission_no', 'balance'], ['OTH-1', '90000']]), $this->term3, false);

    expect($result['problems'][0]['message'])->toContain("No learner has admission number 'OTH-1'")
        ->and($outsider->charges()->exists())->toBeFalse();
});

it('refuses a file without the required columns', function () {
    $check = (new OpeningBalanceCsvImporter($this->school->id))
        ->validate(openingBalancesCsv([['adm', 'amount'], ['ADM-001', '1000']]), $this->term3, false);

    expect($check['valid'])->toBeFalse()
        ->and($check['errors'][0]['message'])->toContain('Missing required columns: admission_no, balance');
});

it('adds the term\'s fees on top of a balance from before the term', function () {
    (new OpeningBalanceCsvImporter($this->school->id))
        ->import(openingBalancesCsv([['admission_no', 'balance'], ['ADM-001', '450000']]), $this->term3, false);

    app(BillingService::class)->billTerm($this->term3);

    expect($this->sarah->balance())->toBe(950000.0);
});

it('does not bill a term again for learners whose old system already charged it', function () {
    $importer = new OpeningBalanceCsvImporter($this->school->id);
    $file = openingBalancesCsv([['admission_no', 'balance'], ['ADM-001', '300000'], ['ADM-002', '0']]);

    expect($importer->validate($file, $this->term3, true)['unbilled_learners'])->toBe(1);

    $importer->import($file, $this->term3, true);
    app(BillingService::class)->billTerm($this->term3);

    expect($this->sarah->balance())->toBe(300000.0)
        ->and($this->brian->balance())->toBe(0.0)
        ->and($this->faith->balance())->toBe(500000.0)
        ->and(StudentCharge::where('student_id', $this->brian->id)->value('description'))->toBe('Fees cleared in previous system');
});

it('counts the term as billed once balances include its fees, so new learners are billed on admission', function () {
    $billing = app(BillingService::class);

    expect($billing->termNeedsBilling($this->term3))->toBeTrue();

    (new OpeningBalanceCsvImporter($this->school->id))
        ->import(openingBalancesCsv([['admission_no', 'balance'], ['ADM-001', '300000']]), $this->term3, true);

    $newcomer = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Ivan Mugisha', 'admission_no' => 'ADM-004', 'status' => 'active']);

    expect($billing->termNeedsBilling($this->term3))->toBeFalse()
        ->and($billing->billNewLearner($newcomer))->toBe(500000.0);
});

it('shows an opening credit on the statement as money already paid', function () {
    (new OpeningBalanceCsvImporter($this->school->id))
        ->import(openingBalancesCsv([['admission_no', 'balance'], ['ADM-002', '-50000']]), $this->term3, false);

    $entry = app(StudentLedger::class)->entries($this->brian)->sole();

    expect($entry)->toMatchArray(['description' => 'Opening credit (from previous system)', 'debit' => 0.0, 'credit' => 50000.0, 'balance' => -50000.0]);
});

it('lets an opening credit pay for the school van', function () {
    $route = TransportRoute::create(['school_id' => $this->school->id, 'name' => 'Kakiri', 'fare' => 80000]);
    StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $this->brian->id, 'term_id' => $this->term3->id, 'transport_route_id' => $route->id, 'description' => 'Transport — Kakiri', 'amount' => 80000, 'discount_amount' => 0, 'charged_on' => now()->toDateString()]);

    (new OpeningBalanceCsvImporter($this->school->id))
        ->import(openingBalancesCsv([['admission_no', 'balance'], ['ADM-002', '-100000']]), $this->term3, false);

    expect((new TransportLedger)->forStudent($this->brian))->toMatchArray(['charged' => 80000.0, 'paid' => 80000.0, 'owed' => 0.0]);
});

it('gives a sheet of the school\'s learners with any balance already brought over', function () {
    $other = School::create(['name' => 'Other School', 'slug' => 'other', 'email' => 'other@example.com', 'school_type' => 'primary']);
    Student::create(['school_id' => $other->id, 'name' => 'Someone Else', 'admission_no' => 'OTH-1', 'status' => 'active']);
    (new OpeningBalanceCsvImporter($this->school->id))
        ->import(openingBalancesCsv([['admission_no', 'balance'], ['ADM-002', '-50000']]), $this->term3, false);

    $lines = array_map('str_getcsv', file(OpeningBalanceCsvImporter::generateTemplate($this->school->id), FILE_IGNORE_NEW_LINES));

    expect($lines)->toBe([
        ['admission_no', 'name', 'class', 'balance'],
        ['ADM-002', 'Brian Okello', 'S.1 East', '-50000'],
        ['ADM-003', 'Faith Achieng', 'S.1 East', ''],
        ['ADM-001', 'Sarah Nakato', 'S.1 East', ''],
    ]);
});

it('imports from the Student Accounts page: upload, check, save', function () {
    $csv = "admission_no,balance\nADM-001,450000\n";

    Livewire::test(ListFeeBalances::class)
        ->set('openingTermId', $this->term3->id)
        ->set('csvFile', UploadedFile::fake()->createWithContent('balances.csv', $csv))
        ->call('uploadAndValidate')
        ->assertSet('importStep', 'preview')
        ->assertSet('validationResult.valid_rows', 1)
        ->call('startImport')
        ->assertSet('importStep', 'complete')
        ->assertSet('importResult.added', 1)
        ->assertNotified('1 opening balance saved');

    expect($this->sarah->balance())->toBe(450000.0);
});

it('rejects a term that belongs to another school', function () {
    $other = School::create(['name' => 'Other School', 'slug' => 'other', 'email' => 'other@example.com', 'school_type' => 'primary']);
    $year = AcademicYear::create(['school_id' => $other->id, 'name' => '2026']);
    $theirTerm = Term::create(['school_id' => $other->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3]);

    Livewire::test(ListFeeBalances::class)
        ->set('openingTermId', $theirTerm->id)
        ->set('csvFile', UploadedFile::fake()->createWithContent('balances.csv', "admission_no,balance\nADM-001,450000\n"))
        ->call('uploadAndValidate')
        ->assertHasErrors(['openingTermId'])
        ->assertSet('importStep', 'upload');

    expect(StudentCharge::count())->toBe(0);
});

it('does not let the browser choose which stored file is imported', function () {
    Storage::disk('local')->put('photos/another-school.jpg', 'not a spreadsheet');

    expect(fn () => Livewire::test(ListFeeBalances::class)->set('storedFile', 'photos/another-school.jpg'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(Storage::disk('local')->exists('photos/another-school.jpg'))->toBeTrue();
});

it('offers the opening balances sheet for download', function () {
    Livewire::test(ListFeeBalances::class)
        ->callAction('downloadOpeningBalances')
        ->assertFileDownloaded('opening-balances.csv');
});

it('keeps the import away from staff without the fees module', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    $this->get(FeeBalanceResource::getUrl('index'))->assertForbidden();
});

// Livewire tests never draw a modal's contents, so the window is drawn here
// directly, as for the staff import: a Blade mistake in it breaks every upload.
it('draws every step of the Import opening balances window', function (string $step, string $expected) {
    $html = view('filament.resources.fee-balances.pages.opening-balances-modal', [
        'importStep' => $step,
        'csvFile' => null,
        'errors' => new ViewErrorBag,
        'terms' => [$this->term3->id => 'Term 3 — 2026 (current)'],
        'termLabel' => 'Term 3 — 2026',
        'includesTermFees' => true,
        'validationResult' => [
            'total_rows' => 3, 'valid_rows' => 1, 'replacing_rows' => 0, 'blank_rows' => 1, 'invalid_rows' => 1, 'error_count' => 1,
            'errors' => [['row' => 3, 'field' => 'balance', 'type' => 'error', 'message' => "Balance 'lots' is not an amount."]],
            'total_owed' => 450000.0, 'total_credit' => 0.0, 'unbilled_learners' => 2,
            'preview' => [['admission_no' => 'ADM-001', 'learner' => 'Sarah Nakato', 'class' => 'S.1 East', 'balance' => '450,000 owed']],
            'unknown_columns' => [],
        ],
        'importResult' => ['added' => 1, 'replaced' => 0, 'removed' => 0, 'skipped' => 1, 'problems' => [['row' => 3, 'field' => 'balance', 'type' => 'error', 'message' => "Balance 'lots' is not an amount."]]],
    ])->render();

    expect($html)->toContain($expected);
})->with([
    'upload' => ['upload', 'My old system already charged this term'],
    'check' => ['preview', '2 active learners'],
    'done' => ['complete', 'Opening balances saved'],
]);
