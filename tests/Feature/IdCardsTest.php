<?php

use App\Filament\Pages\IdCards;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'phone' => '0414 000 111']);
    SubscriptionManager::startTrial($this->school);
    AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $this->guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Okello Peter', 'phone' => '0772123456', 'relationship' => 'father']);

    $this->ready = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'guardian_id' => $this->guardian->id,
        'first_name' => 'Nakato', 'last_name' => 'Grace', 'admission_no' => 'ADM-1', 'gender' => 'female',
        'date_of_birth' => '2016-03-14', 'address' => 'Kira, Wakiso', 'photo' => 'students/nakato.jpg', 'status' => 'active',
    ]);
    $this->incomplete = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $this->class->id,
        'first_name' => 'Mukasa', 'last_name' => 'John', 'admission_no' => 'ADM-2', 'status' => 'active',
    ]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('previews a class and names what each incomplete card is missing', function () {
    Livewire::test(IdCards::class)
        ->set('classId', $this->class->id)
        ->assertOk()
        ->assertSee('NAKATO GRACE')
        ->assertSee('1 is missing details and cannot be printed yet')
        ->assertSee('is missing: photo, date of birth, sex, parent / guardian, guardian phone, home address.', escape: false);
});

it('remembers the card design the school chooses', function () {
    expect($this->school->idCardTemplate())->toBe('classic');

    Livewire::test(IdCards::class)
        ->call('chooseTemplate', 'portrait')
        ->assertSet('template', 'portrait')
        ->assertNotified();

    expect($this->school->fresh()->id_card_template)->toBe('portrait');

    // A later visit opens on the saved design.
    Livewire::test(IdCards::class)->assertSet('template', 'portrait');
});

it('ignores a design that does not exist', function () {
    Livewire::test(IdCards::class)->call('chooseTemplate', 'neon');

    expect($this->school->fresh()->id_card_template)->toBe('classic');
});

it('lets staff without settings access print but not change the design', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    Livewire::test(IdCards::class)
        ->assertOk()
        ->call('chooseTemplate', 'portrait')
        ->assertSet('template', 'classic');

    expect($this->school->fresh()->id_card_template)->toBe('classic');
});

it('refuses to print or export a batch with a card missing details', function () {
    $ids = $this->ready->id.','.$this->incomplete->id;

    $this->get(route('filament.app.students.id-cards.print', ['students' => $ids]))->assertStatus(422);
    $this->get(route('filament.app.students.id-cards.export', ['students' => $ids]))->assertStatus(422);
});

it('prints complete cards in the school\'s saved design', function () {
    $this->school->update(['id_card_template' => 'portrait']);

    $this->get(route('filament.app.students.id-cards.print', ['students' => $this->ready->id]))
        ->assertOk()
        ->assertSee('idc-portrait')
        ->assertSee('NAKATO GRACE')
        ->assertSee('Okello Peter');
});

it('exports complete cards as a PDF in either design', function (string $template) {
    $this->school->update(['id_card_template' => $template]);

    $this->get(route('filament.app.students.id-cards.export', ['students' => $this->ready->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
})->with(['classic', 'portrait']);

it('does not print another school\'s students', function () {
    $other = School::create(['name' => 'Other', 'slug' => 'other', 'email' => 'o@example.com', 'school_type' => 'primary']);
    $stranger = Student::create(['school_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Child', 'admission_no' => 'X-1', 'status' => 'active']);

    $this->get(route('filament.app.students.id-cards.print', ['students' => $stranger->id]))->assertNotFound();
});
