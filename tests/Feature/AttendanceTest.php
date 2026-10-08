<?php

use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\TakeAttendance;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Attendance\AttendanceSummary;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');
    config(['sms.driver' => 'log']);

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'phone' => '0772000111', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->addDays(40)->toDateString()]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $this->east = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'East']);
    $this->west = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'West']);

    $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Nakato Sarah', 'phone' => '0772123456']);
    $this->aisha = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'section_id' => $this->east->id, 'guardian_id' => $guardian->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);
    $this->brian = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'section_id' => $this->east->id, 'name' => 'Brian Ssemanda', 'admission_no' => 'ADM-002', 'status' => 'active']);
    $this->carol = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'section_id' => $this->west->id, 'name' => 'Carol Achieng', 'admission_no' => 'ADM-003', 'status' => 'active']);

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

it('takes the day\'s register and corrects it later the same day', function () {
    $this->actingAs($this->admin);

    Livewire::test(TakeAttendance::class)
        ->set('classId', $this->class->id)
        ->assertSee('Aisha Nakato')
        ->assertSee('Carol Achieng')
        ->assertSet("statuses.{$this->aisha->id}", 'present')
        ->set("statuses.{$this->brian->id}", 'absent')
        ->set("notes.{$this->brian->id}", 'Sick')
        ->set("statuses.{$this->carol->id}", 'late')
        ->call('save')
        ->assertNotified('Register saved');

    expect(AttendanceRecord::count())->toBe(3)
        ->and(AttendanceRecord::where('student_id', $this->brian->id)->value('note'))->toBe('Sick');

    Livewire::test(TakeAttendance::class)
        ->set('classId', $this->class->id)
        ->assertSet('alreadyTaken', true)
        ->assertSet("statuses.{$this->brian->id}", 'absent')
        ->set("statuses.{$this->brian->id}", 'present')
        ->call('save');

    expect(AttendanceRecord::count())->toBe(3)
        ->and(AttendanceRecord::where('student_id', $this->brian->id)->value('status'))->toBe('present');
});

it('will not take a register for a day that has not come', function () {
    $this->actingAs($this->admin);

    Livewire::test(TakeAttendance::class)
        ->set('classId', $this->class->id)
        ->set('date', now()->addDay()->toDateString())
        ->call('save')
        ->assertNotified('That day has not come yet');

    expect(AttendanceRecord::count())->toBe(0);
});

it('limits a class teacher to their own stream', function () {
    $user = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
    $staff = Staff::create(['school_id' => $this->school->id, 'user_id' => $user->id, 'name' => 'Okello James', 'staff_no' => 'ST-1', 'position' => 'Teacher', 'employment_date' => '2020-01-06', 'status' => 'active']);
    $this->east->update(['class_teacher_id' => $staff->id]);

    $this->actingAs($user);

    Livewire::test(TakeAttendance::class)
        ->assertSet('classId', $this->class->id)
        ->assertSet('sectionId', $this->east->id)
        ->assertSee('Aisha Nakato')
        ->assertDontSee('Carol Achieng')
        ->set('sectionId', $this->west->id)
        ->assertDontSee('Carol Achieng')
        ->call('save');

    expect(AttendanceRecord::where('student_id', $this->carol->id)->exists())->toBeFalse();
});

it('lets the class teacher of a class without streams take its whole register', function () {
    $p2 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.2']);
    $dan = Student::create(['school_id' => $this->school->id, 'school_class_id' => $p2->id, 'name' => 'Dan Mukasa', 'admission_no' => 'ADM-010', 'status' => 'active']);

    $user = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
    $staff = Staff::create(['school_id' => $this->school->id, 'user_id' => $user->id, 'name' => 'Auma Grace', 'staff_no' => 'ST-2', 'position' => 'Teacher', 'employment_date' => '2020-01-06', 'status' => 'active']);
    $p2->update(['class_teacher_id' => $staff->id]);

    $this->actingAs($user);

    Livewire::test(TakeAttendance::class)
        ->assertSet('classId', $p2->id)
        ->assertSee('Dan Mukasa')
        ->assertDontSee('Aisha Nakato')
        ->call('save')
        ->assertNotified('Register saved');

    expect(AttendanceRecord::where('student_id', $dan->id)->exists())->toBeTrue();
});

it('keeps the register from users without the attendance module', function () {
    $bursar = User::factory()->create(['school_id' => $this->school->id])->assignRole('Accountant');

    $this->actingAs($bursar)->get(TakeAttendance::getUrl())->assertForbidden();
    $this->actingAs($bursar)->get(AttendanceReport::getUrl())->assertForbidden();
});

it('texts the parents of absent learners once', function () {
    Log::spy();
    $this->actingAs($this->admin);

    $page = Livewire::test(TakeAttendance::class)
        ->set('classId', $this->class->id)
        ->set("statuses.{$this->aisha->id}", 'absent')
        ->set("statuses.{$this->brian->id}", 'absent')
        ->call('save')
        ->callAction('textAbsentParents')
        ->assertNotified('1 parent texted');

    expect(AttendanceRecord::where('student_id', $this->aisha->id)->value('parent_texted_at'))->not->toBeNull();

    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'SMS (log driver)'
        && str_contains($context['message'], 'Aisha Nakato was not at school today')
        && str_contains($context['message'], '0772000111'))->once();

    // A second press does not text the same family again.
    $page->callAction('textAbsentParents')->assertNotified('0 parents texted');
});

it('reports each learner\'s attendance over a period, lowest first', function () {
    foreach (range(1, 4) as $daysAgo) {
        $date = now()->subDays($daysAgo)->toDateString();
        AttendanceRecord::create(['school_id' => $this->school->id, 'student_id' => $this->aisha->id, 'school_class_id' => $this->class->id, 'date' => $date, 'status' => 'present']);
        AttendanceRecord::create(['school_id' => $this->school->id, 'student_id' => $this->brian->id, 'school_class_id' => $this->class->id, 'date' => $date, 'status' => $daysAgo <= 2 ? 'absent' : 'late']);
    }

    $summary = app(AttendanceSummary::class)->forStudents([$this->aisha->id, $this->brian->id]);

    expect($summary[$this->aisha->id])->toMatchArray(['days' => 4, 'present' => 4, 'rate' => 100.0])
        ->and($summary[$this->brian->id])->toMatchArray(['days' => 4, 'present' => 2, 'late' => 2, 'absent' => 2, 'rate' => 50.0]);

    $this->actingAs($this->admin);

    Livewire::test(AttendanceReport::class)
        ->set('classId', $this->class->id)
        ->assertSeeInOrder(['Brian Ssemanda', '50%', 'Aisha Nakato', '100%'])
        ->call('exportCsv')
        ->assertFileDownloaded();
});

it('prints the term\'s attendance on the report card', function () {
    $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'curriculum' => 'primary']);
    $this->class->subjects()->attach($subject->id, ['is_compulsory' => true]);
    $exam = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of Term', 'type' => 'eot', 'max_score' => 100, 'weight' => 100]);
    Mark::create(['assessment_id' => $exam->id, 'student_id' => $this->aisha->id, 'subject_id' => $subject->id, 'score' => 70]);

    foreach (range(1, 3) as $daysAgo) {
        AttendanceRecord::create(['school_id' => $this->school->id, 'student_id' => $this->aisha->id, 'school_class_id' => $this->class->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'status' => $daysAgo === 1 ? 'absent' : 'present']);
    }

    $this->actingAs($this->admin)
        ->get(route('filament.app.academics.report-cards', ['term' => $this->term->id, 'class' => $this->class->id]))
        ->assertOk()
        ->assertSee('2 of 3 days');
});
