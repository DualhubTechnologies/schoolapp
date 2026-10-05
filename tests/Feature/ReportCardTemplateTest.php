<?php

use App\Filament\Pages\ReportCards;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Mark;
use App\Models\ReportCardTemplate;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermReport;
use App\Models\User;
use App\Services\Academics\MarksCompleteness;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'phone' => '0772000111']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2', 'sequence' => 2, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);

    $this->student = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $this->class->id,
        'name' => 'Aisha Nakato', 'first_name' => 'Aisha', 'last_name' => 'Nakato',
        'admission_no' => 'ADM-001', 'gender' => 'female', 'status' => 'active',
    ]);

    $this->bot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'Beginning of term', 'type' => 'bot', 'max_score' => 100, 'sort_order' => 1]);
    $this->eot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of term', 'type' => 'eot', 'max_score' => 100, 'sort_order' => 2]);
    $this->english = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'curriculum' => 'primary']);
    $this->maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'curriculum' => 'primary']);
    $this->class->subjects()->attach([$this->english->id, $this->maths->id]);

    Mark::create(['assessment_id' => $this->bot->id, 'student_id' => $this->student->id, 'subject_id' => $this->english->id, 'score' => 60]);
    Mark::create(['assessment_id' => $this->eot->id, 'student_id' => $this->student->id, 'subject_id' => $this->english->id, 'score' => 70]);
    Mark::create(['assessment_id' => $this->bot->id, 'student_id' => $this->student->id, 'subject_id' => $this->maths->id, 'score' => 55]);
    TermReport::create(['student_id' => $this->student->id, 'term_id' => $this->term->id, 'class_teacher_comment' => 'Works hard.', 'conduct' => 'Good']);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

function printCard(): TestResponse
{
    return test()->get(route('filament.app.academics.report-cards', ['term' => test()->term->id, 'class' => test()->class->id, 'fees' => 1]));
}

it('prints the default parts until the school saves a template', function () {
    $template = ReportCardTemplate::forSchool($this->school->id);

    expect($template->exists)->toBeFalse()
        ->and($template->shownSections())->toHaveCount(count(ReportCardTemplate::SECTIONS) - 2)
        ->and($template->shows('class_position'))->toBeFalse()
        ->and($template->shows('stream_position'))->toBeFalse();

    printCard()->assertOk()
        ->assertSee('Progress Report')
        ->assertDontSee('Position in class')
        ->assertSee('Fees balance')
        ->assertSee('Works hard.')
        ->assertSee('design-classic', false);
});

it('saves the school\'s template and prints with it from then on', function () {
    Livewire::test(ReportCards::class)
        ->callAction('template', data: [
            'design' => 'modern',
            'font' => 'serif',
            'border' => 'ornate',
            'primary_color' => '#0F6B3A',
            'accent_color' => '#D4A017',
            'title' => 'End of Term Two Report',
            'header_note' => 'P.O. Box 12, Mbarara',
            'footer_text' => 'Not valid without the school stamp.',
            'watermark' => false,
            'show' => ['photo', 'assessment_columns', 'conduct', 'class_teacher_comment', 'head_teacher_comment', 'signatures'],
        ])
        ->assertHasNoActionErrors()
        ->assertSet('showFees', false);

    $template = ReportCardTemplate::where('school_id', $this->school->id)->sole();

    expect($template->design)->toBe('modern')
        ->and($template->primary_color)->toBe('#0f6b3a')
        ->and($template->shows('class_position'))->toBeFalse()
        ->and($template->shows('conduct'))->toBeTrue();

    printCard()->assertOk()
        ->assertSee('End of Term Two Report')
        ->assertDontSee('>Progress Report<', false)
        ->assertSee('P.O. Box 12, Mbarara')
        ->assertSee('Not valid without the school stamp.')
        ->assertSee('design-modern font-serif border-ornate', false)
        ->assertSee('#0f6b3a', false)
        ->assertDontSee('Position in class')
        ->assertDontSee('Key:')
        ->assertSee('Works hard.');
});

it('prints the position in class when the school ticks it', function () {
    ReportCardTemplate::create(['school_id' => $this->school->id, ...ReportCardTemplate::DEFAULTS, 'show' => ReportCardTemplate::showFromTicked(['class_position'])]);

    printCard()->assertOk()->assertSee('Position in class');
});

it('leaves the exam columns out when the school hides them', function () {
    ReportCardTemplate::create(['school_id' => $this->school->id, ...ReportCardTemplate::DEFAULTS, 'show' => ReportCardTemplate::showFromTicked(['photo'])]);

    printCard()->assertOk()
        ->assertDontSee('title="Beginning of term"', false)
        ->assertSee('Term %');
});

it('lets only those who manage exams change the template', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    Livewire::test(ReportCards::class)->assertActionHidden('template');
});

it('names the exams that have no marks yet', function () {
    expect(app(MarksCompleteness::class)->missingFor($this->term))->toBe([
        ['class' => 'P.4', 'missing' => ['Mathematics — End of term']],
    ]);

    Mark::create(['assessment_id' => $this->eot->id, 'student_id' => $this->student->id, 'subject_id' => $this->maths->id, 'is_absent' => true]);

    expect(app(MarksCompleteness::class)->missingFor($this->term))->toBeEmpty();
});

it('will not share with parents while exams are missing, unless told to', function () {
    Livewire::test(ReportCards::class)
        ->mountAction('shareWithParents')
        ->callMountedAction(['text_parents' => false, 'share_anyway' => false])
        ->assertHasActionErrors(['share_anyway' => 'accepted']);

    expect($this->term->fresh()->report_cards_released_at)->toBeNull();

    Livewire::test(ReportCards::class)
        ->callAction('shareWithParents', ['text_parents' => false, 'share_anyway' => true])
        ->assertHasNoActionErrors();

    expect($this->term->fresh()->report_cards_released_at)->not->toBeNull();
});

it('searches and filters the class, and prints just the learners shown', function () {
    $brian = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $this->class->id,
        'name' => 'Brian Okello', 'first_name' => 'Brian', 'last_name' => 'Okello',
        'admission_no' => 'ADM-002', 'gender' => 'male', 'status' => 'active',
    ]);
    Mark::create(['assessment_id' => $this->bot->id, 'student_id' => $brian->id, 'subject_id' => $this->english->id, 'score' => 50]);

    $page = Livewire::test(ReportCards::class)
        ->set('classId', $this->class->id)
        ->assertSee('Aisha Nakato')
        ->assertSee('Brian Okello')
        ->set('searchInput', 'okello')
        ->assertSee('Aisha Nakato')
        ->call('applySearch')
        ->assertSee('Brian Okello')
        ->assertDontSee('Aisha Nakato')
        ->assertSee('Print 1 shown');

    expect($page->instance()->printUrl())->toContain('students='.$brian->id);

    $page->call('clearSearch')
        ->set('gender', 'female')
        ->assertSee('Aisha Nakato')
        ->assertDontSee('Brian Okello')
        ->set('gender', '')
        ->set('commentFilter', 'missing')
        ->assertSee('Brian Okello')
        ->assertDontSee('Aisha Nakato')
        ->call('clearFilters')
        ->assertSee('Print all');

    $this->get(route('filament.app.academics.report-cards', ['term' => $this->term->id, 'class' => $this->class->id, 'students' => $brian->id]))
        ->assertOk()
        ->assertSee('Brian Okello')
        ->assertDontSee('Aisha Nakato');
});
