<?php

use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\StudentCsvImporter;
use Illuminate\Support\Facades\Storage;

function templateClasses(School $school): array
{
    $rows = array_map('str_getcsv', file(StudentCsvImporter::generateTemplate($school->id), FILE_IGNORE_NEW_LINES));
    $header = array_shift($rows);

    return array_column(array_map(fn (array $row) => array_combine($header, $row), $rows), 'class');
}

function schoolOfType(string $type): School
{
    return School::create(['name' => "{$type} school", 'slug' => $type, 'email' => "{$type}@example.com", 'school_type' => $type]);
}

it('fills a primary school template with primary classes', function () {
    expect(templateClasses(schoolOfType(School::TYPE_PRIMARY)))
        ->toBe(['P.1', 'P.2', 'P.3', 'P.4', 'P.5', 'P.6', 'P.7']);
});

it('fills a secondary school template with secondary classes', function () {
    expect(templateClasses(schoolOfType(School::TYPE_SECONDARY)))
        ->toBe(['S.1', 'S.2', 'S.3', 'S.4', 'S.5', 'S.6']);
});

it('uses the classes the school has set up, in order', function () {
    $school = schoolOfType(School::TYPE_PRIMARY);
    SchoolClass::create(['school_id' => $school->id, 'name' => 'Top Class', 'level' => 1]);
    SchoolClass::create(['school_id' => $school->id, 'name' => 'P.1', 'level' => 2]);

    expect(templateClasses($school))->toBe(['Top Class', 'P.1']);
});

it('writes the template dates day first, DD-MM-YYYY', function () {
    $rows = array_map('str_getcsv', file(StudentCsvImporter::generateTemplate(schoolOfType(School::TYPE_PRIMARY)->id), FILE_IGNORE_NEW_LINES));
    $row = array_combine(array_shift($rows), $rows[0]);

    expect($row['date_of_birth'])->toMatch('/^15-03-\d{4}$/')
        ->and($row['admission_date'])->toBe('02-02-'.today()->format('Y'));
});

it('checks its own DD-MM-YYYY template clean', function () {
    $school = schoolOfType(School::TYPE_PRIMARY);
    SchoolClass::create(['school_id' => $school->id, 'name' => 'P.1', 'level' => 1]);
    SchoolClass::create(['school_id' => $school->id, 'name' => 'P.2', 'level' => 2]);
    Storage::disk('local')->put('imports/copy.csv', file_get_contents(StudentCsvImporter::generateTemplate($school->id)));

    $import = StudentImport::create(['school_id' => $school->id, 'file_name' => 'copy.csv', 'file_path' => 'imports/copy.csv', 'status' => 'pending', 'imported_by' => User::factory()->create(['school_id' => $school->id])->id]);
    $result = (new StudentCsvImporter($import))->validate();

    expect($result['errors'])->toBe([])
        ->and($result['valid_rows'])->toBe(2);
});

it('puts the school\'s own residencies in the template and imports them', function () {
    $school = schoolOfType(School::TYPE_SECONDARY);
    SchoolClass::create(['school_id' => $school->id, 'name' => 'S.1', 'level' => 1]);
    SchoolClass::create(['school_id' => $school->id, 'name' => 'S.2', 'level' => 2]);
    $boarding = ResidencyType::create(['school_id' => $school->id, 'name' => 'Boarding', 'is_active' => true]);
    $day = ResidencyType::create(['school_id' => $school->id, 'name' => 'Day', 'is_active' => true]);

    $rows = array_map('str_getcsv', file(StudentCsvImporter::generateTemplate($school->id), FILE_IGNORE_NEW_LINES));
    $header = array_shift($rows);

    expect($header)->toContain('residency')
        ->and(array_column(array_map(fn ($r) => array_combine($header, $r), $rows), 'residency'))->toBe(['Boarding', 'Day']);

    Storage::disk('local')->put('imports/res.csv', "first_name,last_name,admission_no,class,residency\nJoan,Nakato,ADM-1,S.1,boarding\nBrian,Okello,ADM-2,S.2,DAY\nGrace,Namuli,ADM-3,S.1,\nIvan,Mugisha,ADM-4,S.1,Hostel\n");
    $import = StudentImport::create(['school_id' => $school->id, 'imported_by' => User::factory()->create(['school_id' => $school->id])->id, 'file_name' => 'res.csv', 'file_path' => 'imports/res.csv', 'status' => 'pending']);

    $check = (new StudentCsvImporter($import))->validate();

    expect($check['valid_rows'])->toBe(3)
        ->and($check['errors'][0]['message'])->toBe("Residency 'Hostel' not found. Use one of: Boarding, Day.");

    (new StudentCsvImporter($import))->import();

    expect(Student::where('admission_no', 'ADM-1')->value('residency_type_id'))->toBe($boarding->id)
        ->and(Student::where('admission_no', 'ADM-2')->value('residency_type_id'))->toBe($day->id)
        ->and(Student::where('admission_no', 'ADM-3')->value('residency_type_id'))->toBeNull()
        ->and(Student::where('admission_no', 'ADM-4')->exists())->toBeFalse();
});

it('leaves residency blank in the template when the school has none', function () {
    $rows = array_map('str_getcsv', file(StudentCsvImporter::generateTemplate(schoolOfType(School::TYPE_PRIMARY)->id), FILE_IGNORE_NEW_LINES));
    $header = array_shift($rows);

    expect(array_unique(array_column(array_map(fn ($r) => array_combine($header, $r), $rows), 'residency')))->toBe(['']);
});
