<?php

use App\Filament\App\Pages\AiInsights;
use App\Filament\App\Pages\Dashboard;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\SchoolInsights;
use App\Services\Subscriptions\SubscriptionManager;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true, 'start_date' => '2026-02-02']);
    $term = fn (int $n, string $start, bool $current = false) => Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => "Term {$n}", 'sequence' => $n, 'is_current' => $current, 'start_date' => $start, 'end_date' => CarbonImmutable::parse($start)->addWeeks(12)]);
    $this->t1 = $term(1, '2026-02-02');
    $this->t2 = $term(2, '2026-05-25');
    $this->t3 = $term(3, '2026-09-14', true);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2']);

    $this->learner = function (string $name): Student {
        static $n = 0;
        $n++;

        return Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => $name, 'admission_no' => "IN-{$n}", 'status' => 'active']);
    };
    $this->charge = fn (Student $s, Term $t, int $amount) => StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $s->id, 'term_id' => $t->id, 'description' => 'Tuition', 'amount' => $amount, 'charged_on' => $t->start_date]);
    $this->pay = fn (Student $s, Term $t, int $amount, string $on) => StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $s->id, 'term_id' => $t->id, 'amount' => $amount, 'paid_on' => $on, 'method' => 'cash']);

    $this->user = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
    $this->actingAs($this->user);
    $this->today = CarbonImmutable::parse('2026-10-15');
    $this->travelTo($this->today->setTime(9, 0));
});

function findings(): array
{
    return collect((new SchoolInsights(test()->school->id, test()->today))->findings())->keyBy('key')->all();
}

it('says how much came in today', function () {
    $aisha = ($this->learner)('Aisha Nakato');
    ($this->pay)($aisha, $this->t3, 200000, '2026-10-15');
    ($this->pay)($aisha, $this->t3, 50000, '2026-10-14');

    expect(findings()['today']['title'])->toBe('UGX 200,000 received today');
});

it('names the learners billed this term who have paid nothing', function () {
    $paid = ($this->learner)('Brian Okello');
    $unpaid = ($this->learner)('Carol Auma');
    ($this->charge)($paid, $this->t3, 800000);
    ($this->charge)($unpaid, $this->t3, 800000);
    ($this->pay)($paid, $this->t3, 100000, '2026-09-20');

    expect(findings()['unpaid'])->toMatchArray(['title' => '1 learner has paid nothing this term', 'detail' => 'Carol Auma (S.2).']);
});

it('points out families that always paid but have not yet this term', function () {
    $faithful = ($this->learner)('Daniel Mugisha');
    foreach ([$this->t1, $this->t2, $this->t3] as $t) {
        ($this->charge)($faithful, $t, 800000);
    }
    ($this->pay)($faithful, $this->t1, 800000, '2026-02-10');
    ($this->pay)($faithful, $this->t2, 800000, '2026-06-01');

    expect(findings()['usual-payers']['title'])->toBe('1 family that always pays has not paid yet this term');
});

it('compares fee collection with last term at the same point', function () {
    $s = ($this->learner)('Esther Namubiru');
    ($this->charge)($s, $this->t2, 1000000);
    ($this->charge)($s, $this->t3, 1000000);
    ($this->pay)($s, $this->t2, 600000, '2026-06-10');   // 60% by week 5 last term
    ($this->pay)($s, $this->t3, 200000, '2026-09-25');   // 20% by week 5 this term

    expect(findings()['pace'])->toMatchArray([
        'tone' => 'warning',
        'title' => 'Fee collection is behind last term',
        'detail' => "20% of this term's fees collected by week 5, against 60% at the same point last term.",
    ]);
});

it('flags the same amount paid twice for a learner on one day', function () {
    $s = ($this->learner)('Frank Kato');
    ($this->pay)($s, $this->t3, 300000, '2026-10-01');
    ($this->pay)($s, $this->t3, 300000, '2026-10-01');

    expect(findings()['doubles'])->toMatchArray(['tone' => 'danger', 'title' => '1 possible double payment to check']);
});

it('names learners absent three or more days this past week', function () {
    $s = ($this->learner)('Grace Atim');
    foreach (['2026-10-12', '2026-10-13', '2026-10-14'] as $day) {
        AttendanceRecord::create(['school_id' => $this->school->id, 'student_id' => $s->id, 'school_class_id' => $this->class->id, 'date' => $day, 'status' => 'absent']);
    }

    expect(findings()['absent']['detail'])->toBe('Grace Atim (S.2).');
});

it('names learners whose average fell ten points or more since last term', function () {
    $falling = ($this->learner)('Henry Ssali');
    $steady = ($this->learner)('Irene Apio');
    $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTC', 'curriculum' => 'o_level']);
    $exam = fn (Term $t) => Assessment::create(['school_id' => $this->school->id, 'term_id' => $t->id, 'name' => 'End of term', 'type' => 'exam', 'max_score' => 100, 'class_ids' => [$this->class->id], 'subject_ids' => [$subject->id]]);
    $e2 = $exam($this->t2);
    $e3 = $exam($this->t3);
    $mark = fn (Assessment $e, Student $s, int $score) => Mark::create(['assessment_id' => $e->id, 'student_id' => $s->id, 'subject_id' => $subject->id, 'score' => $score]);
    $mark($e2, $falling, 80);
    $mark($e3, $falling, 62);
    $mark($e2, $steady, 70);
    $mark($e3, $steady, 68);

    expect(findings()['marks']['detail'])->toContain('Henry Ssali (S.2)')->not->toContain('Irene Apio');
});

it('pops the findings up on the dashboard once a day, and not when there is nothing to say', function () {
    $this->get(Dashboard::getUrl())->assertOk()->assertDontSee('aria-labelledby="shi-pop-title"', false);
    expect($this->user->fresh()->insights_seen_on)->toBeNull(); // nothing shown, so not used up

    ($this->pay)(($this->learner)('Joan Nakimera'), $this->t3, 150000, '2026-10-15');
    cache()->flush();

    $this->get(Dashboard::getUrl())->assertOk()
        ->assertSee('aria-labelledby="shi-pop-title"', false)
        ->assertSee('AI Insights')
        ->assertSee('UGX 150,000 received today');

    // Signing in again or reopening the dashboard the same day: no pop-up.
    $this->get(Dashboard::getUrl())->assertOk()->assertDontSee('aria-labelledby="shi-pop-title"', false);

    // The next day it pops up again.
    $this->travelTo($this->today->addDay()->setTime(9, 0));
    ($this->pay)(($this->learner)('Kato Peter'), $this->t3, 90000, '2026-10-16');

    $this->get(Dashboard::getUrl())->assertOk()->assertSee('aria-labelledby="shi-pop-title"', false);
});

it('lists every finding under AI Insights in the menu, at any time', function () {
    ($this->pay)(($this->learner)('Joan Nakimera'), $this->t3, 150000, '2026-10-15');
    $this->user->forceFill(['insights_seen_on' => '2026-10-15'])->save(); // pop-up already seen

    $this->get(Dashboard::getUrl())->assertOk()
        ->assertDontSee('aria-labelledby="shi-pop-title"', false)
        ->assertSee(AiInsights::getUrl());

    $this->get(AiInsights::getUrl())->assertOk()->assertSee('UGX 150,000 received today');
});

it('says all is clear on AI Insights when there is nothing to point out', function () {
    $this->get(AiInsights::getUrl())->assertOk()->assertSee('Nothing needs your attention right now');
});

it('keeps AI Insights to the head and the bursar', function () {
    $teacher = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');

    $this->actingAs($teacher)->get(AiInsights::getUrl())->assertForbidden();
});
