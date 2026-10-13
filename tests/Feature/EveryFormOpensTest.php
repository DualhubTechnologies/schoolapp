<?php

use App\Models\School;
use App\Models\User;
use App\Services\SchoolStarterSetup;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Resource;
use Livewire\Livewire;

// Opens every add and edit form, in the school panel for a school set up
// with every section and in the platform owner's panel, so a form that
// cannot draw (a list of options built from a bad query, a missing
// relationship) fails here, not in front of a school.

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->school = School::create(['name' => 'Kisubi High', 'slug' => 'kisubi-high', 'email' => 'kisubi@example.test', 'school_type' => 'secondary']);
    app(SchoolStarterSetup::class)->run($this->school, ['nursery', 'primary', 'o_level', 'a_level'], 'mixed');

});

// Read from the folder, not the panel: datasets are built before the app
// starts.
function resourcesIn(string $panel): array
{
    return collect(glob(__DIR__."/../../app/Filament/{$panel}/Resources/*/*Resource.php"))
        ->mapWithKeys(fn (string $file): array => [
            basename($file, '.php') => [sprintf('App\\Filament\\%s\\Resources\\%s\\%s', $panel, basename(dirname($file)), basename($file, '.php'))],
        ])
        ->all();
}

/** @param  class-string<resource>  $resource */
function opensEveryForm(string $resource): void
{
    if (! $resource::canAccess()) {
        test()->markTestSkipped('Switched off for this school.');
    }

    $pages = collect($resource::getPages())->map(fn ($registration) => $registration->getPage());
    $record = $resource::getEloquentQuery()->first();

    if ($create = $pages->first(fn (string $page) => is_subclass_of($page, CreateRecord::class))) {
        Livewire::test($create)->assertOk();
    }

    if ($record && ($edit = $pages->first(fn (string $page) => is_subclass_of($page, EditRecord::class)))) {
        Livewire::test($edit, ['record' => $record->getRouteKey()])->assertOk();
    }

    if ($manage = $pages->first(fn (string $page) => is_subclass_of($page, ManageRecords::class))) {
        $page = Livewire::test($manage)->assertOk();

        if ($page->instance()->getAction('create')?->isVisible()) {
            $page->mountAction(CreateAction::class)->assertOk();
        }

        if ($record) {
            Livewire::test($manage)->mountAction(TestAction::make(EditAction::class)->table($record))->assertOk();
        }
    }

    expect($pages)->not->toBeEmpty();
}

it('opens the add and edit forms in the school panel', function (string $resource) {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    Filament::setCurrentPanel('app');

    opensEveryForm($resource);
})->with(resourcesIn('App'));

it('opens the add and edit forms in the platform owner panel', function (string $resource) {
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));
    Filament::setCurrentPanel('admin');

    opensEveryForm($resource);
})->with(resourcesIn('Admin'));
