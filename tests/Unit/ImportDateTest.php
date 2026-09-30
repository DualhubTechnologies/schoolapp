<?php

use App\Support\ImportDate;

it('reads the ways a spreadsheet writes a date, day first', function (string $typed, ?string $expected) {
    expect(ImportDate::parse($typed))->toBe($expected);
})->with([
    'DD-MM-YYYY' => ['14-03-2012', '2012-03-14'],
    'no leading zeros' => ['4-3-2012', '2012-03-04'],
    'slashes' => ['14/03/2012', '2012-03-14'],
    'dots' => ['14.03.2012', '2012-03-14'],
    'two-digit year' => ['14-03-12', '2012-03-14'],
    'two-digit year last century' => ['14-03-85', '1985-03-14'],
    'day first when both could be months' => ['05-03-2012', '2012-03-05'],
    'month first only when it must be' => ['03/14/2012', '2012-03-14'],
    'year first' => ['2012-03-14', '2012-03-14'],
    'month name' => ['14-Mar-2012', '2012-03-14'],
    'month name, spaces' => ['14 March 2012', '2012-03-14'],
    'Excel day number' => ['40982', '2012-03-14'],
    'no such date' => ['31-02-2012', null],
    'nonsense' => ['next week', null],
    'blank' => ['', null],
]);
