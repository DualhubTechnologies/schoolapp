<?php

use App\Filament\App\Resources\Terms\Pages\CreateTerm;
use App\Filament\App\Resources\Terms\Pages\EditTerm;
use App\Filament\App\Resources\Terms\TermResource;
use App\Filament\Support\Pages\CreateRecordPage;
use App\Filament\Support\Pages\EditRecordPage;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $this->year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('builds every create and edit page on the shared form pages', function () {
    $pages = collect(['app', 'admin'])
        ->flatMap(fn (string $panel) => Filament::getPanel($panel)->getResources())
        ->flatMap(fn (string $resource) => collect($resource::getPages())->map->getPage());

    $creates = $pages->filter(fn (string $page) => is_subclass_of($page, CreateRecord::class));
    $edits = $pages->filter(fn (string $page) => is_subclass_of($page, EditRecord::class));

    expect($creates)->not->toBeEmpty()
        ->and($edits)->not->toBeEmpty()
        ->and($creates->reject(fn (string $page) => is_subclass_of($page, CreateRecordPage::class))->values()->all())->toBe([])
        ->and($edits->reject(fn (string $page) => is_subclass_of($page, EditRecordPage::class))->values()->all())->toBe([]);
});

it('saves a new record and returns to the list', function () {
    Livewire::test(CreateTerm::class)
        ->assertActionDoesNotExist('createAnother')
        ->fillForm(['academic_year_id' => $this->year->id, 'name' => 'Term 1', 'sequence' => 1])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(TermResource::getUrl('index'));

    expect(Term::where('school_id', $this->school->id)->where('name', 'Term 1')->exists())->toBeTrue();
});

it('saves changes and returns to the list', function () {
    $term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'name' => 'Term 1', 'sequence' => 1]);

    Livewire::test(EditTerm::class, ['record' => $term->getRouteKey()])
        ->fillForm(['name' => 'First term'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(TermResource::getUrl('index'));

    expect($term->refresh()->name)->toBe('First term');
});

it('labels the button Save and links back to the list', function () {
    $this->get(TermResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Back to Terms')
        ->assertSee('Save')
        ->assertDontSee('Save &amp; add another', false)
        ->assertDontSee('Create &amp; create another', false);
});
