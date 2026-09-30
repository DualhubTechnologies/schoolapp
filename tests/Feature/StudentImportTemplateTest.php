<?php

use App\Models\School;
use App\Models\SchoolClass;
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
