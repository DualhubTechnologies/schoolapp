<?php

use App\Filament\App\Resources\Combinations\Pages\ManageCombinations;
use App\Filament\App\Resources\GradingScales\Pages\ManageGradingScales;
use App\Models\Combination;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolStarterSetup;
use Database\Seeders\RoleSeeder;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

// Filament hands a relationship's query to the callback by the name
// $query; any other name got an empty query and the window failed.

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->school = School::create(['name' => 'Kisubi High', 'slug' => 'kisubi-high', 'email' => 'kisubi@example.test', 'school_type' => 'secondary']);
    app(SchoolStarterSetup::class)->run($this->school, ['o_level', 'a_level'], 'day');
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    Filament::setCurrentPanel('app');
});

it('edits an A-Level combination', function () {
    $combination = Combination::where('school_id', $this->school->id)->firstOrFail();

    Livewire::test(ManageCombinations::class)
        ->mountAction(TestAction::make(EditAction::class)->table($combination))
        ->assertOk()
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($combination->subjects()->count())->toBe(3);
});

it('edits a grading scale, its grades highest first', function () {
    $scale = GradingScale::where('school_id', $this->school->id)->firstOrFail();

    Livewire::test(ManageGradingScales::class)
        ->mountAction(TestAction::make(EditAction::class)->table($scale))
        ->assertOk()
        ->callMountedAction()
        ->assertHasNoActionErrors();
});
