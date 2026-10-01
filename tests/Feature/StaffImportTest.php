<?php

use App\Filament\App\Resources\Staff\Pages\ListStaff;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffBankDetail;
use App\Models\StaffSalary;
use App\Models\User;
use App\Services\StaffCsvImporter;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Light Secondary', 'slug' => 'light', 'email' => 'light@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

/** Write CSV lines to the local disk and return the stored path. */
function staffCsv(array $rows): string
{
    $lines = array_map(fn (array $r): string => implode(',', array_map(fn ($v) => str_contains((string) $v, ',') ? '"'.$v.'"' : $v, $r)), $rows);
    $file = 'imports/staff-test-'.uniqid().'.csv';
    Storage::disk('local')->put($file, implode("\n", $lines)."\n");

    return $file;
}

it('gives a template that checks clean and imports every example', function () {
    $path = StaffCsvImporter::generateTemplate($this->school->id);
    $file = 'imports/template-copy.csv';
    Storage::disk('local')->put($file, file_get_contents($path));

    $importer = new StaffCsvImporter($this->school->id);
    $check = $importer->validate($file);

    expect($check['valid'])->toBeTrue()
        ->and($check['total_rows'])->toBe(5)
        ->and($check['valid_rows'])->toBe(5);

    $result = $importer->import($file);

    expect($result['imported'])->toBe(5)
        ->and(Staff::where('school_id', $this->school->id)->count())->toBe(5)
        ->and(Staff::where('staff_no', 'ST-004')->first()->category)->toBe('non_teaching')
        ->and(Staff::where('staff_no', 'ST-005')->first()->pays_lst)->toBeFalse()
        ->and((float) StaffSalary::whereHas('staff', fn ($q) => $q->where('staff_no', 'ST-002'))->value('base_salary'))->toBe(850000.0)
        ->and(StaffBankDetail::whereHas('staff', fn ($q) => $q->where('staff_no', 'ST-001'))->value('payment_method'))->toBe('bank_transfer')
        ->and(StaffBankDetail::whereHas('staff', fn ($q) => $q->where('staff_no', 'ST-003'))->value('mobile_money_provider'))->toBe('MTN');
});

it('names each row that needs fixing and imports nothing from it', function () {
    $file = staffCsv([
        ['name', 'staff_no', 'position', 'category', 'employment_date', 'basic_salary', 'phone'],
        ['Okello Brian', 'ST-1', 'Teacher', 'Teaching', '2024-02-01', '850,000', '772100001'],
        ['Nakato Sarah', 'ST-2', '', 'Cleaner', '31/13/2024', 'lots', ''],
        ['Mugisha Ivan', 'ST-3', 'Teacher', '', '', '', ''],
        ['Kato Samuel', 'ST-3', 'Driver', 'Non-teaching', '', '', ''],
    ]);

    $check = (new StaffCsvImporter($this->school->id))->validate($file);
    $messages = collect($check['errors'])->pluck('message')->implode(' | ');

    expect($check['valid_rows'])->toBe(1)
        ->and($check['invalid_rows'])->toBe(3)
        ->and($messages)->toContain('Position (job title) is required')
        ->and($messages)->toContain("Category must be 'Teaching' or 'Non-teaching'")
        ->and($messages)->toContain('Invalid employment date')
        ->and($messages)->toContain('Basic salary must be an amount')
        ->and($messages)->toContain("Staff number 'ST-3' appears 2 times");

    (new StaffCsvImporter($this->school->id))->import($file);

    expect(Staff::pluck('staff_no')->all())->toBe(['ST-1'])
        ->and(Staff::first()->phone)->toBe('0772100001')
        ->and(Staff::first()->employment_date->format('Y-m-d'))->toBe('2024-02-01');
});

it('skips staff the school already has, but not another school\'s numbers', function () {
    Staff::create(['school_id' => $this->school->id, 'name' => 'Existing Person', 'staff_no' => 'ST-001', 'position' => 'Teacher', 'employment_date' => '2020-01-01']);

    $other = School::create(['name' => 'Other School', 'slug' => 'other', 'email' => 'other@example.com', 'school_type' => 'primary']);
    Staff::create(['school_id' => $other->id, 'name' => 'Someone Else', 'staff_no' => 'ST-002', 'position' => 'Teacher', 'employment_date' => '2020-01-01']);

    $file = staffCsv([
        ['name', 'staff_no', 'position'],
        ['Okello Brian', 'st-001', 'Teacher'],
        ['Nakato Sarah', 'ST-002', 'Teacher'],
    ]);

    $check = (new StaffCsvImporter($this->school->id))->validate($file);

    expect($check['existing_rows'])->toBe(1)
        ->and($check['existing'][0]['message'])->toContain('Existing Person')
        ->and($check['valid_rows'])->toBe(1);

    $result = (new StaffCsvImporter($this->school->id))->import($file);

    expect($result['imported'])->toBe(1)
        ->and(Staff::where('school_id', $this->school->id)->where('staff_no', 'ST-002')->exists())->toBeTrue();
});

it('refuses a file without the required columns', function () {
    $file = staffCsv([['full_name', 'number'], ['Okello Brian', '1']]);

    $check = (new StaffCsvImporter($this->school->id))->validate($file);

    expect($check['valid'])->toBeFalse()
        ->and($check['errors'][0]['message'])->toContain('Missing required columns: name, staff_no, position');
});

it('imports from the Staff page: upload, check, import', function () {
    $csv = "name,staff_no,position,category\nOkello Brian,ST-9,Teacher,Teaching\n";

    Livewire::test(ListStaff::class)
        ->set('csvFile', UploadedFile::fake()->createWithContent('staff.csv', $csv))
        ->call('uploadAndValidate')
        ->assertSet('importStep', 'preview')
        ->assertSet('validationResult.valid_rows', 1)
        ->call('startImport')
        ->assertSet('importStep', 'complete')
        ->assertSet('importResult.imported', 1);

    expect(Staff::where('school_id', $this->school->id)->where('staff_no', 'ST-9')->exists())->toBeTrue();
});

it('offers the staff CSV template for download', function () {
    Livewire::test(ListStaff::class)
        ->callAction('downloadTemplate')
        ->assertFileDownloaded('staff-import-template.csv');
});

// Livewire tests never draw a modal's contents, so the Import staff window
// is drawn here directly: once, a Blade mistake in it broke every upload.
it('draws every step of the Import staff window', function (string $step) {
    $html = view('filament.resources.staff.pages.import-modal', [
        'importStep' => $step,
        'csvFile' => null,
        'errors' => new ViewErrorBag,
        'validationResult' => [
            'total_rows' => 2, 'valid_rows' => 1, 'invalid_rows' => 1, 'existing_rows' => 0, 'error_count' => 1,
            'errors' => [['row' => 3, 'field' => 'name', 'type' => 'error', 'message' => 'Name is required.']],
            'existing' => [],
            'preview' => [['name' => 'Okello Brian', 'staff_no' => 'ST-1', 'position' => 'Teacher', 'category' => 'Teaching', 'department' => '', 'phone' => '', 'basic_salary' => '']],
            'unknown_columns' => [],
        ],
        'importResult' => ['imported' => 1, 'skipped' => 1, 'problems' => [['row' => 3, 'field' => 'name', 'type' => 'error', 'message' => 'Name is required.']]],
    ])->render();

    expect($html)->toContain(match ($step) {
        'upload' => 'Check file',
        'preview' => 'Okello Brian',
        'complete' => 'Import completed',
    });
})->with(['upload', 'preview', 'complete']);
