<?php

use App\Filament\App\Resources\LibraryBooks\Pages\ManageLibraryBooks;
use App\Filament\App\Resources\LibraryLoans\LibraryLoanResource;
use App\Filament\App\Resources\LibraryLoans\Pages\ManageLibraryLoans;
use App\Models\AcademicYear;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryLoan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\User;
use App\Services\SchoolInsights;
use App\Services\Subscriptions\SubscriptionManager;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-19 09:00:00');
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => '2026-09-14', 'end_date' => '2026-12-04']);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2']);

    $this->reader = function (string $name): Student {
        static $n = 0;
        $n++;

        return Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => $name, 'admission_no' => "LIB-T{$n}", 'status' => 'active']);
    };

    $this->librarian = User::factory()->create(['school_id' => $this->school->id])->assignRole('Librarian');
    $this->actingAs($this->librarian);
});

it('adds a book with numbered copies, carrying the numbers on to the next book', function () {
    Livewire::test(ManageLibraryBooks::class)
        ->callAction('create', data: ['title' => 'Comprehensive Biology Bk 2', 'category' => 'textbook', 'price' => 45000, 'copies' => 3])
        ->assertHasNoActionErrors();

    $biology = LibraryBook::where('title', 'Comprehensive Biology Bk 2')->sole();
    expect($biology->copies()->orderBy('accession_no')->pluck('accession_no')->all())->toBe(['LIB-0001', 'LIB-0002', 'LIB-0003']);

    $novel = LibraryBook::create(['school_id' => $this->school->id, 'title' => 'The River Between']);
    expect($novel->addCopies(2)[0]->accession_no)->toBe('LIB-0004');
});

it('lends a book to a learner by its number, and takes it back', function () {
    $book = LibraryBook::create(['school_id' => $this->school->id, 'title' => 'Oxford Atlas']);
    [$copy] = $book->addCopies(1);
    $sarah = ($this->reader)('Sarah Nakato');

    Livewire::test(ManageLibraryLoans::class)
        ->callAction('lend', data: ['library_copy_id' => $copy->id, 'who' => 'learner', 'student_id' => $sarah->id, 'due_on' => '2026-11-02'])
        ->assertHasNoActionErrors();

    $loan = LibraryLoan::sole();
    expect($loan->student_id)->toBe($sarah->id)
        ->and($loan->term_id)->toBe($this->term->id)
        ->and($copy->fresh()->status)->toBe(LibraryCopy::ON_LOAN);

    Livewire::test(ManageLibraryLoans::class)
        ->callAction(TestAction::make('return')->table($loan), data: ['condition' => 'fair', 'returned_on' => '2026-10-19'])
        ->assertHasNoActionErrors();

    expect($loan->fresh()->returned_on->toDateString())->toBe('2026-10-19')
        ->and($copy->fresh()->status)->toBe(LibraryCopy::AVAILABLE)
        ->and($copy->fresh()->condition)->toBe('fair');
});

it('lends a textbook to a whole class while copies last, and not twice', function () {
    $book = LibraryBook::create(['school_id' => $this->school->id, 'title' => 'MK Mathematics Bk 2']);
    $book->addCopies(2);
    foreach (['Agnes', 'Brian', 'Carol'] as $name) {
        ($this->reader)($name);
    }

    Livewire::test(ManageLibraryBooks::class)
        ->callAction(TestAction::make('issueToClass')->table($book), data: ['school_class_id' => $this->class->id, 'due_on' => '2026-12-04'])
        ->assertHasNoActionErrors()
        ->assertNotified('2 copies issued');

    expect(LibraryLoan::outstanding()->count())->toBe(2)
        ->and(LibraryLoan::with('student')->get()->pluck('student.name')->sort()->values()->all())->toBe(['Agnes', 'Brian']);

    $book->addCopies(1);
    Livewire::test(ManageLibraryBooks::class)
        ->callAction(TestAction::make('issueToClass')->table($book->fresh()), data: ['school_class_id' => $this->class->id])
        ->assertNotified('1 copy issued');

    expect(LibraryLoan::outstanding()->count())->toBe(3);
});

it('bills a lost book to the learner\'s fees, but not a member of staff', function () {
    $book = LibraryBook::create(['school_id' => $this->school->id, 'title' => 'Comprehensive Biology Bk 2', 'price' => 45000]);
    [$one, $two] = $book->addCopies(2);
    $sarah = ($this->reader)('Sarah Nakato');
    $teacher = Staff::create(['school_id' => $this->school->id, 'name' => 'Okello James', 'staff_no' => 'ST-1', 'position' => 'Teacher', 'employment_date' => '2024-01-01']);

    $learnerLoan = LibraryLoan::create(['school_id' => $this->school->id, 'library_copy_id' => $one->id, 'student_id' => $sarah->id, 'issued_on' => '2026-09-20']);
    $staffLoan = LibraryLoan::create(['school_id' => $this->school->id, 'library_copy_id' => $two->id, 'staff_id' => $teacher->id, 'issued_on' => '2026-09-20']);

    Livewire::test(ManageLibraryLoans::class)
        ->callAction(TestAction::make('lost')->table($learnerLoan), data: ['bill' => true, 'amount' => 45000])
        ->assertHasNoActionErrors();

    $charge = StudentCharge::sole();
    expect($charge->description)->toBe('Lost library book: Comprehensive Biology Bk 2 ('.$one->accession_no.')')
        ->and((float) $charge->amount)->toBe(45000.0)
        ->and($charge->term_id)->toBe($this->term->id)
        ->and($sarah->fresh()->balance())->toEqual(45000.0)
        ->and($one->fresh()->status)->toBe(LibraryCopy::LOST);

    Livewire::test(ManageLibraryLoans::class)
        ->callAction(TestAction::make('lost')->table($staffLoan), data: []);

    expect(StudentCharge::count())->toBe(1)
        ->and($staffLoan->fresh()->lost_on)->not->toBeNull();
});

it('points out books not returned: overdue ones, and all of them near the end of term', function () {
    $book = LibraryBook::create(['school_id' => $this->school->id, 'title' => 'Oxford Atlas']);
    [$a, $b] = $book->addCopies(2);
    $late = ($this->reader)('Late Lucy');
    $onTime = ($this->reader)('On-time Otim');
    LibraryLoan::create(['school_id' => $this->school->id, 'library_copy_id' => $a->id, 'student_id' => $late->id, 'issued_on' => '2026-09-20', 'due_on' => '2026-10-10']);
    LibraryLoan::create(['school_id' => $this->school->id, 'library_copy_id' => $b->id, 'student_id' => $onTime->id, 'issued_on' => '2026-10-15', 'due_on' => '2026-12-04']);
    $admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
    $this->actingAs($admin);

    $now = collect((new SchoolInsights($this->school->id, CarbonImmutable::parse('2026-10-19')))->findings())->keyBy('key');
    expect($now['library']['title'])->toBe('1 library book not returned on time')
        ->and($now['library']['detail'])->toContain('Late Lucy')->not->toContain('On-time Otim')
        ->and($now['library']['url'])->toBe(LibraryLoanResource::getUrl());

    $endOfTerm = collect((new SchoolInsights($this->school->id, CarbonImmutable::parse('2026-11-25')))->findings())->keyBy('key');
    expect($endOfTerm['library']['title'])->toBe('2 library books not returned before the end of term');
});

it('opens the library to the librarian and not to a teacher', function () {
    $this->get(LibraryLoanResource::getUrl())->assertOk()->assertSee('Issue a book');

    $teacher = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
    $this->actingAs($teacher)->get(LibraryLoanResource::getUrl())->assertForbidden();
});
