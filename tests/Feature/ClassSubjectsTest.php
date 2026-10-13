<?php

use App\Filament\App\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\App\Resources\SchoolClasses\RelationManagers\SubjectsRelationManager;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\SchoolStarterSetup;
use Database\Seeders\RoleSeeder;
use Filament\Actions\AttachAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('adds a subject to a class from the class page', function () {
    $this->seed(RoleSeeder::class);
    $school = School::create(['name' => 'Kisubi High', 'slug' => 'kisubi-high', 'email' => 'kisubi@example.test', 'school_type' => 'secondary']);
    app(SchoolStarterSetup::class)->run($school, ['o_level'], 'day');
    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));
    Filament::setCurrentPanel('app');

    $class = SchoolClass::where('school_id', $school->id)->first();
    $class->subjects()->detach();
    $subject = Subject::where('school_id', $school->id)->where('curriculum', $class->curriculum())->first();

    // "Add subject" leaves out the class's own subjects through
    // Subject::classes(); it used to look for schoolClasses() and fail.
    Livewire::test(SubjectsRelationManager::class, ['ownerRecord' => $class, 'pageClass' => EditSchoolClass::class])
        ->mountAction(TestAction::make(AttachAction::class)->table())
        ->setActionData(['recordId' => [$subject->id], 'is_compulsory' => true])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($class->subjects()->whereKey($subject->id)->exists())->toBeTrue();
});
