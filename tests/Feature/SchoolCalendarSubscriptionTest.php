<?php

use App\Filament\Admin\Resources\SchoolCalendarTerms\Pages\ManageSchoolCalendarTerms;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolCalendarTerm;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Services\Subscriptions\TermCalendar;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->plan = Plan::create(['name' => 'Calendar Standard', 'slug' => 'calendar-standard', 'max_students' => 800, 'max_users' => 25, 'price_per_term' => 240000, 'price_per_year' => 648000, 'is_trial' => false, 'is_active' => true, 'sort_order' => 1]);
    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'status' => 'active']);
});

/** The last day a $cycle paid on $date covers. */
function coveredTo(string $date, string $cycle = 'term'): string
{
    return SubscriptionManager::periodEnd(CarbonImmutable::parse($date), $cycle)->toDateString();
}

it('runs a paid term to the day before the next school term opens', function () {
    expect(coveredTo('2026-10-01'))->toBe('2027-01-31')
        ->and(TermCalendar::coveredTerm(CarbonImmutable::parse('2026-10-01'))->label())->toBe('Term 3, 2026')
        ->and(coveredTo('2026-12-20'))->toBe('2027-05-23')
        ->and(coveredTo('2026-10-01', 'year'))->toBe('2027-09-12');
});

it('covers the next term when paid in the last weeks of a term', function () {
    expect(coveredTo('2026-11-20'))->toBe('2027-05-23')
        ->and(TermCalendar::coveredTerm(CarbonImmutable::parse('2026-11-20'))->label())->toBe('Term 1, 2027');
});

it('counts months when the calendar does not reach that far', function () {
    expect(coveredTo('2028-03-01'))->toBe('2028-06-30')
        ->and(coveredTo('2026-10-01', 'custom'))->toBe('2027-01-31');

    SchoolCalendarTerm::query()->delete();

    expect(coveredTo('2026-10-01'))->toBe('2027-01-31')
        ->and(coveredTo('2026-10-05'))->toBe('2027-02-04');
});

it('renews on the calendar and stacks the next payment on the following term', function () {
    $this->travelTo('2026-10-22 09:00:00');

    $first = SubscriptionManager::renew($this->school, $this->plan, 'term', 240000);
    [$from, $to] = SubscriptionManager::nextPeriod($this->school, $this->plan, 'term');
    $second = SubscriptionManager::renew($this->school, $this->plan, 'term', 240000);

    expect($first->starts_on->toDateString())->toBe('2026-10-22')
        ->and($first->ends_on->toDateString())->toBe('2027-01-31')
        ->and([$from->toDateString(), $to->toDateString()])->toBe(['2027-02-01', '2027-05-23'])
        ->and($second->starts_on->toDateString())->toBe('2027-02-01')
        ->and($second->ends_on->toDateString())->toBe('2027-05-23');
});

it('lets the platform owner keep the calendar up to date', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));
    $term = SchoolCalendarTerm::where(['year' => 2027, 'term' => 1])->sole();

    Livewire::test(ManageSchoolCalendarTerms::class)
        ->assertCanSeeTableRecords([$term])
        ->callTableAction('edit', $term, data: ['opens_on' => '2027-02-08', 'closes_on' => '2027-05-07', 'is_estimate' => false])
        ->assertHasNoTableActionErrors();

    expect($term->fresh()->is_estimate)->toBeFalse()
        ->and(coveredTo('2026-12-20', 'term'))->toBe('2027-05-23')
        ->and(coveredTo('2026-10-01'))->toBe('2027-02-07');
});
