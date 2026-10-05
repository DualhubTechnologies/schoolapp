<?php

use App\Filament\App\Resources\SyllabusTopics\Pages\ManageSyllabusTopics;
use App\Filament\Pages\AssessTopics;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassLevel;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\Term;
use App\Models\TopicScore;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $level = ClassLevel::firstOrCreate(['school_id' => $this->school->id, 'name' => 'O-Level'], ['curriculum' => 'o_level']);
    $level->update(['curriculum' => 'o_level']);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'class_level_id' => $level->id, 'name' => 'S.2']);
    $this->chemistry = Subject::create(['school_id' => $this->school->id, 'name' => 'Chemistry', 'curriculum' => 'o_level']);
    $this->class->subjects()->attach($this->chemistry->id, ['is_compulsory' => true]);

    $this->sarah = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Sarah Nakato', 'admission_no' => 'ADM-T001', 'status' => 'active']);

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
    $this->actingAs($this->admin);
});

it('adds a pasted list of syllabus topics with their numbers', function () {
    $added = ManageSyllabusTopics::addTopics($this->chemistry->id, 2, "Topic 5: Acids and alkalis\nT6 Salts\n7. Carbon in the environment\n\nTopic 5: Acids and alkalis");

    expect($added)->toBe(3)
        ->and(SyllabusTopic::orderBy('sort_order')->get(['code', 'name', 'class_number'])->toArray())->toBe([
            ['code' => 'T5', 'name' => 'Acids and alkalis', 'class_number' => 2],
            ['code' => 'T6', 'name' => 'Salts', 'class_number' => 2],
            ['code' => 'T7', 'name' => 'Carbon in the environment', 'class_number' => 2],
        ]);
});

it('records topic levels and turns their average into the Topic assessment mark', function () {
    ManageSyllabusTopics::addTopics($this->chemistry->id, 2, "T5 Acids and alkalis\nT6 Salts\nT7 Carbon in the environment");
    [$t5, $t6, $t7] = SyllabusTopic::orderBy('sort_order')->pluck('id')->all();

    Livewire::test(AssessTopics::class)
        ->set('classId', $this->class->id)
        ->set('subjectId', $this->chemistry->id)
        ->assertSee(['Acids and alkalis', 'Sarah Nakato'])
        ->set("levels.{$this->sarah->id}.{$t5}", '1')
        ->set("levels.{$this->sarah->id}.{$t6}", '2')
        ->set("levels.{$this->sarah->id}.{$t7}", '3')
        ->call('save')
        ->assertNotified('Topic levels saved');

    $assessment = Assessment::where('type', 'topics')->sole();

    expect(TopicScore::count())->toBe(3)
        ->and((float) $assessment->max_score)->toBe(3.0)
        ->and((float) $assessment->weight)->toBe(20.0)
        ->and((float) Mark::where('assessment_id', $assessment->id)->where('student_id', $this->sarah->id)->value('score'))->toBe(2.0);

    // Clearing a level takes it out of the average.
    Livewire::test(AssessTopics::class)
        ->set('classId', $this->class->id)
        ->set('subjectId', $this->chemistry->id)
        ->assertSet("levels.{$this->sarah->id}.{$t5}", '1')
        ->set("levels.{$this->sarah->id}.{$t5}", '')
        ->call('save');

    expect(TopicScore::count())->toBe(2)
        ->and((float) Mark::where('assessment_id', $assessment->id)->value('score'))->toBe(2.5);
});

it('prints topic levels and what needs support on the report card', function () {
    ManageSyllabusTopics::addTopics($this->chemistry->id, 2, "T5 Acids and alkalis\nT6 Salts");
    [$t5, $t6] = SyllabusTopic::orderBy('sort_order')->pluck('id')->all();
    $exam = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of year', 'type' => 'eot', 'max_score' => 100, 'weight' => 80]);
    Mark::create(['assessment_id' => $exam->id, 'student_id' => $this->sarah->id, 'subject_id' => $this->chemistry->id, 'score' => 60]);

    Livewire::test(AssessTopics::class)
        ->set('classId', $this->class->id)
        ->set('subjectId', $this->chemistry->id)
        ->set("levels.{$this->sarah->id}.{$t5}", '1')
        ->set("levels.{$this->sarah->id}.{$t6}", '3')
        ->call('save');

    $this->get(route('filament.app.academics.report-cards', ['term' => $this->term->id, 'class' => $this->class->id]))
        ->assertOk()
        ->assertSee('Topics (0–3)')
        ->assertSee('Topics achieved (level 2 or 3)')
        ->assertSee('1 of 2')
        ->assertSee('Chemistry T5');
});

it('keeps Assess Topics from users without the exams work', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Staff'));

    $this->get(AssessTopics::getUrl())->assertForbidden();
});
