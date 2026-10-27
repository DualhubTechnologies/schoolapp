<?php

use App\Filament\App\Resources\Assessments\Pages\ManageAssessments;
use App\Filament\App\Resources\Guardians\GuardianResource;
use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\App\Resources\SyllabusTopics\Pages\ManageSyllabusTopics;
use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\ClassResults;
use App\Filament\Pages\ReportCards;
use App\Filament\Pages\Reports\EnrolmentReport;
use App\Filament\Pages\TakeAttendance;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * What a subject teacher and a class teacher see and may do: their own
 * learners and classes only, and nothing that is the office's job.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');
    $this->withoutVite();

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->addDays(40)->toDateString()]);

    $this->s2 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2']);
    $this->s4 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.4']);
    $this->east = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->s2->id, 'name' => 'East']);
    $this->geo = Subject::create(['school_id' => $this->school->id, 'name' => 'Geography', 'curriculum' => 'o_level']);
    $this->bio = Subject::create(['school_id' => $this->school->id, 'name' => 'Biology', 'curriculum' => 'o_level']);

    $parent = Guardian::create(['school_id' => $this->school->id, 'name' => 'Okello Peter', 'phone' => '0772000001']);
    $other = Guardian::create(['school_id' => $this->school->id, 'name' => 'Other Parent', 'phone' => '0772000002']);
    $this->mine = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->s2->id, 'section_id' => $this->east->id, 'guardian_id' => $parent->id, 'name' => 'Okello Brian', 'admission_no' => 'A-1', 'status' => 'active']);
    $this->notMine = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->s4->id, 'guardian_id' => $other->id, 'name' => 'Senior Learner', 'admission_no' => 'A-2', 'status' => 'active']);

    $teacherAs = function (bool $classTeacher): User {
        $user = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
        $staff = Staff::create(['school_id' => $this->school->id, 'user_id' => $user->id, 'name' => $user->name, 'staff_no' => 'ST-'.$user->id, 'position' => 'Teacher', 'employment_date' => '2024-01-01']);
        DB::table('class_subject')->insert(['school_class_id' => $this->s2->id, 'subject_id' => $classTeacher ? $this->bio->id : $this->geo->id, 'teacher_id' => $staff->id]);

        if ($classTeacher) {
            $this->east->update(['class_teacher_id' => $staff->id]);
        }

        return $user;
    };

    $this->teacher = $teacherAs(false);
    $this->classTeacher = $teacherAs(true);
});

it('shows a teacher only the learners and parents of the classes they teach, read-only', function () {
    $this->actingAs($this->teacher);

    Livewire::test(ListStudents::class)
        ->assertCanSeeTableRecords([$this->mine])
        ->assertCanNotSeeTableRecords([$this->notMine])
        ->assertDontSee('Add parent');

    expect(StudentResource::getNavigationBadge())->toBe('1')
        ->and(GuardianResource::getEloquentQuery()->pluck('name')->all())->toBe(['Okello Peter'])
        ->and(StudentResource::canEdit($this->mine))->toBeFalse();

    $this->get(StudentResource::getUrl('view', ['record' => $this->notMine]))->assertNotFound();
});

it('keeps the register, report cards and whole-school reports for those whose job they are', function () {
    $this->actingAs($this->teacher);
    expect(TakeAttendance::canAccess())->toBeFalse()
        ->and(ReportCards::canAccess())->toBeFalse()
        ->and(EnrolmentReport::canAccess())->toBeFalse()
        ->and(Livewire::test(ClassResults::class)->instance()->classOptions()->keys()->all())->toBe([$this->s2->id])
        ->and(Livewire::test(AttendanceReport::class)->instance()->classOptions()->keys()->all())->toBe([$this->s2->id]);

    Livewire::test(ManageAssessments::class)->assertActionHidden('create');

    $this->actingAs($this->classTeacher);
    expect(TakeAttendance::canAccess())->toBeTrue()
        ->and(ReportCards::canAccess())->toBeTrue();
    Livewire::test(TakeAttendance::class)->assertSet('classId', $this->s2->id)->assertSet('sectionId', $this->east->id);

    // The office still sees everyone.
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    expect(StudentResource::getNavigationBadge())->toBe('2')
        ->and(EnrolmentReport::canAccess())->toBeTrue();
});

it('lets a teacher keep the syllabus topics of their own subjects only', function () {
    $geoTopic = SyllabusTopic::create(['school_id' => $this->school->id, 'subject_id' => $this->geo->id, 'class_number' => 2, 'code' => 'T5', 'name' => 'Map reading']);
    $bioTopic = SyllabusTopic::create(['school_id' => $this->school->id, 'subject_id' => $this->bio->id, 'class_number' => 2, 'code' => 'T5', 'name' => 'Nutrition in plants']);

    $this->actingAs($this->teacher);
    Livewire::test(ManageSyllabusTopics::class)
        ->assertCanSeeTableRecords([$geoTopic])
        ->assertCanNotSeeTableRecords([$bioTopic])
        ->assertTableActionVisible('edit', $geoTopic);
});
