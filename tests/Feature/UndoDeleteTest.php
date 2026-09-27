<?php

use App\Filament\App\Resources\Houses\Pages\EditHouse;
use App\Filament\App\Resources\Students\Pages\EditStudent;
use App\Models\House;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\UndoDelete;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('puts back a deleted record that took nothing with it', function () {
    $house = House::create(['school_id' => $this->school->id, 'name' => 'Kabaka', 'capacity' => 40, 'is_active' => true]);

    Livewire::test(EditHouse::class, ['record' => $house->getRouteKey()])
        ->callAction('delete')
        ->assertNotified('Kabaka deleted');

    expect(House::find($house->id))->toBeNull();

    $snapshot = UndoDelete::snapshot($house, 'Kabaka');
    $this->get(route('filament.app.undo-delete', ['token' => UndoDelete::remember($snapshot)]))->assertRedirect();

    expect(House::find($house->id)?->name)->toBe('Kabaka')
        ->and(House::find($house->id)->capacity)->toBe(40);
});

it('does not offer undo when the delete took other records with it', function () {
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    Section::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Blue']);

    expect(UndoDelete::snapshot($class, 'P.4'))->toBeNull();
});

it('lets nobody else use the undo, and only once', function () {
    $house = House::create(['school_id' => $this->school->id, 'name' => 'Muteesa']);
    $token = UndoDelete::remember(UndoDelete::snapshot($house, 'Muteesa'));
    $house->delete();

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id]));
    expect(UndoDelete::restore($token))->toBeNull()
        ->and(House::find($house->id))->toBeNull();
});

it('shows who changed a record on its edit page', function () {
    $student = Student::create(['school_id' => $this->school->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);
    $student->update(['admission_no' => 'ADM-009']);

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->assertActionVisible('history')
        ->mountAction('history')
        ->assertMountedActionModalSee(['Who changed this?', 'ADM-001', 'ADM-009']);
});
