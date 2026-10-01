<?php

use App\Console\Commands\BackupRun;
use App\Models\AcademicYear;
use App\Models\School;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * The Windows app keeps its data in SQLite rather than the server's
 * MySQL. These check the parts that differ between the two.
 */
// Runs on whichever database the tests use: SQLite locally, MySQL on GitHub.
it('groups dates by month on either database', function () {
    expect(DB::selectOne('select '.Sql::yearMonth("'2026-09-14'").' as ym')->ym)->toBe('2026-09');
});

it('backs up a SQLite database as a consistent, compressed copy', function () {
    $original = config('database.default');
    $dir = storage_path('framework/testing/desktop-db');
    File::ensureDirectoryExists($dir);
    $path = $dir.'/schoolhub.sqlite';
    File::put($path, '');

    config([
        'database.connections.desktop' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true],
        'database.default' => 'desktop',
    ]);
    DB::purge('desktop');
    DB::statement('create table learners (id integer primary key, name text)');
    DB::insert('insert into learners (name) values (?)', ['Aisha Nakato']);

    $before = File::glob(BackupRun::folder().'/schoolhub-*');
    $this->artisan('backup:run', ['--no-files' => true])->assertSuccessful();

    $backup = collect(File::glob(BackupRun::folder().'/schoolhub-db-*.sqlite.gz'))->diff($before)->sole();
    $restored = $dir.'/restored.sqlite';
    File::put($restored, gzdecode(File::get($backup)));

    expect(substr(File::get($restored), 0, 15))->toBe('SQLite format 3');

    config(['database.connections.restored' => ['driver' => 'sqlite', 'database' => $restored, 'prefix' => '']]);
    expect(DB::connection('restored')->table('learners')->value('name'))->toBe('Aisha Nakato');

    // Back to the test database before the files go: the test run tidies
    // up on the default connection afterwards.
    config(['database.default' => $original]);
    DB::purge('restored');
    DB::purge('desktop');
    File::deleteDirectory($dir);
    File::delete($backup);
});

it('saves date-only values as plain dates on SQLite, so lookups by day work', function () {
    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'start_date' => now()->setDate(2026, 2, 2), 'end_date' => '2026-12-04 00:00:00', 'is_current' => true]);

    expect(DB::table('academic_years')->where('id', $year->id)->value('start_date'))->toBe('2026-02-02')
        ->and(DB::table('academic_years')->where('id', $year->id)->value('end_date'))->toBe('2026-12-04')
        ->and(AcademicYear::where('end_date', '<=', '2026-12-04')->whereKey($year->id)->exists())->toBeTrue()
        ->and($year->fresh()->start_date->format('j M Y'))->toBe('2 Feb 2026');
});
