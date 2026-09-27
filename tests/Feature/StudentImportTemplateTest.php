<?php

use App\Models\School;
use App\Models\SchoolClass;
use App\Services\StudentCsvImporter;

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
