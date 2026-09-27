<?php

use App\Filament\App\Pages\Dashboard;
use App\Models\AcademicYear;
use App\Models\Combination;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\SchoolStarterSetup;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-26 10:00'));
});

function newSchool(string $type = School::TYPE_PRIMARY): School
{
    $name = fake()->unique()->company().' School';

    return School::create([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'email' => fake()->unique()->safeEmail(),
        'school_type' => $type,
    ]);
}

function adminOf(School $school, string $role = 'School Admin'): User
{
    return User::factory()->create(['school_id' => $school->id])->assignRole($role);
}

it('sets up a nursery and primary school with classes but no streams', function () {
    $school = newSchool();

    $result = app(SchoolStarterSetup::class)->run($school, ['nursery', 'primary'], 'mixed');

    expect(SchoolClass::where('school_id', $school->id)->orderBy('level')->pluck('name')->all())
        ->toBe(['Baby Class', 'Middle Class', 'Top Class', 'P.1', 'P.2', 'P.3', 'P.4', 'P.5', 'P.6', 'P.7'])
        ->and(Section::where('school_id', $school->id)->count())->toBe(0)
        ->and(ResidencyType::where('school_id', $school->id)->orderBy('id')->pluck('name')->all())->toBe(['Day', 'Boarding'])
        ->and(Subject::where('school_id', $school->id)->where('curriculum', 'primary')->exists())->toBeTrue()
        ->and($result['classes'])->toBe(10)
        ->and($school->fresh()->setup_choice)->toBe(SchoolStarterSetup::CHOICE_RECOMMENDED)
        ->and($school->fresh()->boarding_type)->toBe('mixed');
});

it('adds the Ministry calendar with the right term current', function () {
    $school = newSchool();

    app(SchoolStarterSetup::class)->run($school, ['primary'], 'day');

    $year = AcademicYear::where('school_id', $school->id)->sole();
    $terms = Term::where('school_id', $school->id)->orderBy('sequence')->get();

    expect($year->name)->toBe('2026')
        ->and($year->is_current)->toBeTrue()
        ->and($terms->pluck('name')->all())->toBe(['Term 1', 'Term 2', 'Term 3'])
        ->and($terms[0]->start_date->toDateString())->toBe('2026-02-02')
        ->and($terms[2]->end_date->toDateString())->toBe('2026-12-04')
        ->and($terms->where('is_current', true)->pluck('name')->all())->toBe(['Term 3']);
});

it('keeps a term current through the holiday that follows it', function (string $today, int $expected) {
    $terms = SchoolStarterSetup::termsFor(Carbon::parse($today));

    expect(SchoolStarterSetup::currentTermIndex($terms, Carbon::parse($today)))->toBe($expected);
})->with([
    'before the year opens' => ['2026-01-15', 0],
    'first-term holiday' => ['2026-05-10', 0],
    'second term' => ['2026-06-01', 1],
    'third term' => ['2026-09-26', 2],
]);

it('falls back to the usual calendar for a year the Ministry has not published', function () {
    $terms = SchoolStarterSetup::termsFor(Carbon::parse('2027-03-01'));

    expect(SchoolStarterSetup::hasOfficialCalendar(Carbon::parse('2027-03-01')))->toBeFalse()
        ->and(collect($terms)->map(fn ($t) => $t['starts']->toDateString())->all())
        ->toBe(['2027-02-01', '2027-05-25', '2027-09-14']);
});

it('sets up only the secondary sections ticked', function () {
    $school = newSchool(School::TYPE_SECONDARY);

    app(SchoolStarterSetup::class)->run($school, ['o_level'], 'boarding');

    expect(SchoolClass::where('school_id', $school->id)->orderBy('level')->pluck('name')->all())->toBe(['S.1', 'S.2', 'S.3', 'S.4'])
        ->and(Combination::where('school_id', $school->id)->count())->toBe(0)
        ->and(ResidencyType::where('school_id', $school->id)->orderBy('id')->pluck('name')->all())->toBe(['Boarding']);
});

it('adds A-Level combinations when A-Level is ticked', function () {
    $school = newSchool(School::TYPE_SECONDARY);

    app(SchoolStarterSetup::class)->run($school, ['o_level', 'a_level'], 'day');

    expect(SchoolClass::where('school_id', $school->id)->count())->toBe(6)
        ->and(Combination::where('school_id', $school->id)->where('name', 'PCM')->exists())->toBeTrue();
});

it('ignores sections outside the school type and never duplicates on a second run', function () {
    $school = newSchool();
    $setup = app(SchoolStarterSetup::class);

    $setup->run($school, ['primary', 'a_level'], 'day');
    $second = $setup->run($school, ['primary'], 'day');

    expect(SchoolClass::where('school_id', $school->id)->count())->toBe(7)
        ->and(Term::where('school_id', $school->id)->count())->toBe(3)
        ->and($second['classes'])->toBe(0);
});

it('offers the setup to a new school admin on the dashboard', function () {
    $this->actingAs(adminOf(newSchool()));
    Filament::setCurrentPanel('app');

    Livewire::test(Dashboard::class)
        ->assertSet('defaultAction', 'schoolSetup')
        ->assertActionVisible('schoolSetup');
});

it('applies the recommended setup from the dashboard', function () {
    $school = newSchool();
    $this->actingAs(adminOf($school));
    Filament::setCurrentPanel('app');

    Livewire::test(Dashboard::class)
        ->callAction('schoolSetup', data: ['sections' => ['primary'], 'boarding_type' => 'day'])
        ->assertHasNoActionErrors()
        ->assertRedirect(Dashboard::getUrl());

    expect(SchoolClass::where('school_id', $school->id)->count())->toBe(7)
        ->and($school->fresh()->setup_choice)->toBe(SchoolStarterSetup::CHOICE_RECOMMENDED);
});

it('lets the admin skip and does not ask again', function () {
    $school = newSchool();
    $this->actingAs(adminOf($school));
    Filament::setCurrentPanel('app');

    Livewire::test(Dashboard::class)
        ->callAction('schoolSetup', arguments: ['skip' => true])
        ->assertRedirect(Dashboard::getUrl());

    expect($school->fresh()->setup_choice)->toBe(SchoolStarterSetup::CHOICE_SKIPPED)
        ->and(SchoolClass::where('school_id', $school->id)->count())->toBe(0);

    Livewire::test(Dashboard::class)->assertSet('defaultAction', null);
});

it('does not offer the setup to schools that have answered, or to other staff', function () {
    $answered = newSchool();
    $answered->update(['setup_completed_at' => now(), 'setup_choice' => 'existing']);

    expect(SchoolStarterSetup::isOfferedTo(adminOf($answered)))->toBeFalse()
        ->and(SchoolStarterSetup::isOfferedTo(adminOf(newSchool(), 'Teacher')))->toBeFalse()
        ->and(SchoolStarterSetup::isOfferedTo(User::factory()->create()->assignRole('Super Admin')))->toBeFalse();
});

it('describes the grading for the school type', function (bool $isPrimary, string $shown, string $hidden) {
    $terms = SchoolStarterSetup::termsFor();

    $html = view('filament.app.setup.whats-included', [
        'terms' => $terms,
        'currentTerm' => 'Term 3',
        'officialCalendar' => true,
        'year' => 2026,
        'isPrimary' => $isPrimary,
    ])->render();

    expect($html)->toContain($shown)->not->toContain($hidden);
})->with([
    'primary' => [true, 'PLE grading scale and divisions', 'UACE'],
    'secondary' => [false, 'UCE and UACE grading scales', 'PLE'],
]);
