<?php

use App\Exports\ReportExport;
use App\Filament\App\Pages\Dashboard;
use App\Filament\Pages\Reports\AllReports;
use App\Filament\Pages\Reports\EnrolmentReport;
use App\Filament\Pages\Reports\FeeCollectionsReport;
use App\Filament\Pages\Reports\FeesByClassReport;
use App\Models\AcademicYear;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Menu;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => '2026-09-14', 'end_date' => '2026-12-04']);
    $this->s1 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1', 'level' => 1]);
    $this->s2 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2', 'level' => 2]);
    $this->east = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->s1->id, 'name' => 'East']);
    $this->day = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Day', 'is_active' => true]);
    $this->boarding = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Boarding', 'is_active' => true]);

    $learner = function (array $data): Student {
        static $n = 0;
        $n++;

        return Student::create($data + ['school_id' => $this->school->id, 'name' => "Learner {$n}", 'admission_no' => "R-{$n}", 'status' => 'active']);
    };

    $this->aisha = $learner(['school_class_id' => $this->s1->id, 'section_id' => $this->east->id, 'gender' => 'female', 'residency_type_id' => $this->boarding->id, 'is_new_learner' => true]);
    $this->brian = $learner(['school_class_id' => $this->s1->id, 'section_id' => $this->east->id, 'gender' => 'male', 'residency_type_id' => $this->day->id]);
    $this->carol = $learner(['school_class_id' => $this->s2->id, 'gender' => 'female', 'residency_type_id' => $this->day->id]);
    $learner(['school_class_id' => $this->s2->id, 'gender' => 'male', 'residency_type_id' => $this->day->id, 'status' => 'left']);

    $charge = fn (Student $student, int $amount, int $discount = 0) => StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'term_id' => $this->term->id, 'description' => 'Tuition', 'amount' => $amount, 'discount_amount' => $discount, 'charged_on' => '2026-09-14']);
    $charge($this->aisha, 1250000, 250000);
    $charge($this->brian, 850000);
    $charge($this->carol, 850000);

    $pay = fn (Student $student, int $amount, string $on, string $method) => StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'term_id' => $this->term->id, 'amount' => $amount, 'paid_on' => $on, 'method' => $method]);
    $pay($this->aisha, 1000000, '2026-09-15', 'bank');
    $pay($this->brian, 300000, '2026-09-15', 'cash');
    $pay($this->carol, 200000, '2026-09-20', 'mobile_money');
    $pay($this->carol, 50000, '2026-09-20', 'cash')->void('Typed twice');

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('arranges the menu in groups, daily work first and the set-up folded at the end', function () {
    $this->get(Dashboard::getUrl())->assertOk()->assertSeeInOrder(['Students', 'Fees', 'Exams &amp; Results', 'Finance', 'Staff &amp; Payroll', 'Reports', 'School Setup', 'Settings'], false);

    expect(collect(Menu::ITEMS)->pluck(0)->unique()->diff(array_keys(Menu::GROUPS)))->toBeEmpty()
        ->and(array_filter(Menu::GROUPS))->toBe(['School Setup' => true, 'Settings' => true]);
});

it('lists every report a school administrator can open, by area', function () {
    $areas = AllReports::available();

    expect(array_keys($areas))->toBe(['Students', 'Fees', 'Finance', 'Exams & Results', 'Staff & Payroll'])
        ->and(collect($areas['Fees'])->pluck('title')->all())->toContain('Fee collections', 'Fees by class');

    Livewire::test(AllReports::class)->assertSee('Enrolment')->assertSee('Income vs expenditure');
});

it('shows a teacher only the reports of their work, never the fees', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    expect(array_keys(AllReports::available()))->not->toContain('Fees')->not->toContain('Finance')
        ->and(FeeCollectionsReport::canAccess())->toBeFalse();

    $this->get(FeesByClassReport::getUrl())->assertForbidden();
});

it('counts the learners per class and stream, by sex, residency and new', function () {
    $rows = collect(Livewire::test(EnrolmentReport::class)->instance()->rows())->keyBy('label');

    expect($rows['S.1']['total'])->toBe(2)
        ->and($rows['S.1 East'])->toMatchArray(['kind' => 'stream', 'boys' => 1, 'girls' => 1])
        ->and($rows['S.1']['residency'])->toBe([$this->day->id => 1, $this->boarding->id => 1])
        ->and($rows['S.2']['total'])->toBe(1) // the learner who left is not counted
        ->and($rows['Total'])->toMatchArray(['boys' => 1, 'girls' => 2, 'total' => 3, 'new' => 1]);
});

it('adds up the fees received day by day and by way of paying, leaving out voided receipts', function () {
    $report = Livewire::test(FeeCollectionsReport::class)->set('from', '2026-09-14')->set('to', '2026-09-30');
    $days = collect($report->instance()->byDay())->keyBy('label');

    expect($days['Tue 15 Sep 2026'])->toMatchArray(['receipts' => 2, 'total' => 1300000.0])
        ->and($days['Tue 15 Sep 2026']['methods'])->toMatchArray(['bank' => 1000000.0, 'cash' => 300000.0])
        ->and($days['Total'])->toMatchArray(['receipts' => 3, 'total' => 1500000.0])
        ->and(collect($report->instance()->byClass())->pluck('total', 'label')->all())->toBe(['S.1' => 1300000.0, 'S.2' => 200000.0]);

    $report->set('to', '2026-09-15');
    expect(collect($report->instance()->byDay())->last()['total'])->toBe(1300000.0);
});

it('sets the usual periods in one tap', function () {
    $this->travelTo('2026-10-14');

    Livewire::test(FeeCollectionsReport::class)
        ->call('setRange', 'today')->assertSet('from', '2026-10-14')->assertSet('to', '2026-10-14')
        ->call('setRange', 'month')->assertSet('from', '2026-10-01')
        ->call('setRange', 'term')->assertSet('from', '2026-09-14')->assertSee('This term');
});

it('shows each class\'s fees billed, paid and owed for the term', function () {
    $rows = collect(Livewire::test(FeesByClassReport::class)->instance()->rows())->keyBy('label');

    // S.1: 1,000,000 (after a 250,000 bursary) + 850,000 billed; 1,000,000 + 300,000 paid.
    expect($rows['S.1'])->toMatchArray(['learners' => 2, 'billed' => 1850000.0, 'paid' => 1300000.0, 'balance' => 550000.0, 'percent' => 70, 'owing' => 1, 'cleared' => 1])
        ->and($rows['S.2'])->toMatchArray(['billed' => 850000.0, 'paid' => 200000.0, 'percent' => 23])
        ->and($rows['Total'])->toMatchArray(['learners' => 3, 'billed' => 2700000.0, 'paid' => 1500000.0, 'balance' => 1200000.0]);
});

it('downloads a report for Excel', function () {
    Excel::fake();

    Livewire::test(FeesByClassReport::class)->call('downloadExcel');

    Excel::assertDownloaded('Fees_by_Class_Term_3_2026.xlsx', function (ReportExport $export): bool {
        return $export->headings()[1] === ['Fees by Class - Term 3 - 2026']
            && $export->array()[2][0] === 'Total';
    });
});

it('keeps the report styles apart from the Receive Payment page', function () {
    // Receive Payment styles its own rp-* classes; the shared stylesheet
    // must not (its report rules once turned the balance card sideways).
    expect(file_get_contents(public_path('css/filament-custom.css')))->not->toMatch('/\.rp-[a-z]/');
});
