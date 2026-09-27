<?php

use App\Filament\App\Resources\Students\Pages\EditStudent;
use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Filament\App\Resources\Students\Schemas\StudentForm;
use App\Filament\App\Resources\Students\StudentResource;
use App\Models\Guardian;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.1']);
    $this->day = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Day', 'is_active' => true]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

function admit(array $data, array $arguments = [])
{
    return Livewire::test(ListStudents::class)
        ->callAction(CreateAction::class, data: [
            'first_name' => 'Aisha',
            'last_name' => 'Nakato',
            'gender' => 'female',
            'school_class_id' => test()->class->id,
            'residency_type_id' => test()->day->id,
            ...$data,
        ], arguments: $arguments);
}

it('admits a learner from one short screen with a new parent', function () {
    admit(['new_guardian_name' => 'Sarah Nakato', 'new_guardian_phone' => '0772 555666', 'new_guardian_relationship' => 'mother'])
        ->assertHasNoFormErrors();

    $student = Student::where('school_id', $this->school->id)->sole();

    expect($student->name)->toBe('Aisha Nakato')
        ->and($student->admission_no)->not->toBeEmpty()
        ->and($student->status)->toBe('active')
        ->and($student->guardian->only(['name', 'phone', 'relationship']))
        ->toBe(['name' => 'Sarah Nakato', 'phone' => '0772 555666', 'relationship' => 'mother']);
});

it('needs a parent: either an existing family or a new name and phone', function () {
    admit([])->assertHasFormErrors(['new_guardian_name' => 'required', 'new_guardian_phone' => 'required']);

    expect(Student::count())->toBe(0);
});

it('finds the family from a brother or sister and reuses their parent', function () {
    $mother = Guardian::create(['school_id' => $this->school->id, 'name' => 'Sarah Nakato', 'phone' => '0772555666', 'relationship' => 'mother']);
    Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'guardian_id' => $mother->id, 'name' => 'Brian Okello', 'admission_no' => '001', 'status' => 'active']);

    $found = StudentForm::searchFamilies('Brian');
    expect($found)->toHaveKey($mother->id)
        ->and($found[$mother->id])->toContain('parent of Brian Okello (P.1)');

    admit(['guardian_id' => $mother->id])->assertHasNoFormErrors();

    expect(Student::where('first_name', 'Aisha')->sole()->guardian_id)->toBe($mother->id)
        ->and(Guardian::count())->toBe(1);
});

it('does not enter a family twice when the same phone is typed again', function () {
    $mother = Guardian::create(['school_id' => $this->school->id, 'name' => 'Sarah Nakato', 'phone' => '0772555666']);

    admit(['new_guardian_name' => 'Sarah N.', 'new_guardian_phone' => '+256 772 555 666'])->assertHasNoFormErrors();

    expect(Guardian::count())->toBe(1)
        ->and(Student::where('first_name', 'Aisha')->sole()->guardian_id)->toBe($mother->id);
});

it('opens the full profile after "Admit & add more details", saying what is missing', function () {
    admit(['new_guardian_name' => 'Sarah Nakato', 'new_guardian_phone' => '0772555666'], ['edit' => true])
        ->assertHasNoFormErrors()
        ->assertRedirect(StudentResource::getUrl('edit', ['record' => Student::where('first_name', 'Aisha')->sole()]));

    $student = Student::where('first_name', 'Aisha')->sole();

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->assertSee('Profile 33% complete')
        ->assertSee('still to add: photo, date of birth, LIN, home address.');
});

it('asks for a stream only when the class has streams', function () {
    Livewire::test(ListStudents::class)
        ->mountAction(CreateAction::class)
        ->fillForm(['school_class_id' => $this->class->id])
        ->assertFormFieldHidden('section_id');

    Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Blue']);

    Livewire::test(ListStudents::class)
        ->mountAction(CreateAction::class)
        ->fillForm(['school_class_id' => $this->class->id])
        ->assertFormFieldVisible('section_id');
});

it('prints a formal admission letter addressed to the parent', function () {
    $mother = Guardian::create(['school_id' => $this->school->id, 'name' => 'SARAH NAKATO', 'phone' => '0772555666']);
    $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'residency_type_id' => $this->day->id, 'guardian_id' => $mother->id, 'first_name' => 'aisha', 'last_name' => 'nakato', 'admission_no' => 'ADM-007', 'status' => 'active', 'schoolpay_code' => '1004567890']);

    $html = view('pdf.admission-letter', [
        'student' => $student->load('school', 'guardian', 'schoolClass', 'residencyType'),
        'school' => $this->school,
        'term' => null,
        'feeLines' => collect([(object) ['name' => 'Tuition', 'amount' => 370000]]),
        'feeTotal' => 370000,
        'logoPath' => null,
        'signaturePath' => null,
    ])->render();

    expect($html)
        ->toContain('The Parent / Guardian of <strong>Aisha Nakato</strong>')
        ->toContain('Dear Sarah Nakato,')
        ->toContain('Offer of admission')
        ->toContain('ADM/007/')
        ->toContain('UGX 370,000')
        ->toContain('Pay with code <strong>1004567890</strong>')
        ->toContain('Acceptance of admission')
        ->toContain('Provisional');

    $this->get(route('filament.app.students.admission-letter', $student))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
