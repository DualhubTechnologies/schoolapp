<?php

use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\Assets\Pages\ManageAssets;
use App\Filament\App\Resources\StoreItems\Pages\ManageStoreItems;
use App\Filament\App\Resources\StoreItems\StoreItemResource;
use App\Filament\App\Resources\StoreMovements\Pages\ListStoreMovements;
use App\Models\AcademicYear;
use App\Models\Asset;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\School;
use App\Models\StoreItem;
use App\Models\StoreMovement;
use App\Models\Term;
use App\Models\User;
use App\Services\SchoolInsights;
use App\Services\Stores\StoreLedger;
use App\Services\Subscriptions\SubscriptionManager;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-25 09:00:00');
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => '2026-09-14', 'end_date' => '2026-12-04']);

    $this->storekeeper = User::factory()->create(['school_id' => $this->school->id])->assignRole('Storekeeper');
    $this->actingAs($this->storekeeper);

    $this->posho = StoreItem::create(['school_id' => $this->school->id, 'name' => 'Maize flour (posho)', 'category' => 'food', 'unit' => 'kg', 'reorder_level' => 200]);
});

it('receives stock from a supplier and records the expense in Finance', function () {
    Livewire::test(ManageStoreItems::class)
        ->callTableAction('receive', $this->posho, data: [
            'quantity' => 500, 'unit_cost' => 2800, 'date' => '2026-10-24', 'supplier' => 'Nakawa Millers', 'reference' => 'DN-114',
            'record_expense' => true, 'method' => 'bank',
        ])
        ->assertHasNoTableActionErrors();

    $this->posho->refresh();
    $entry = FinanceEntry::sole();

    expect((float) $this->posho->quantity)->toBe(500.0)
        ->and((float) $this->posho->unit_cost)->toBe(2800.0)
        ->and((float) $entry->amount)->toBe(1400000.0)
        ->and($entry->type)->toBe('expense')
        ->and($entry->category->name)->toBe('Feeding & meals')
        ->and($entry->party)->toBe('Nakawa Millers')
        ->and($entry->method)->toBe('bank')
        ->and($entry->description)->toBe('Stores: 500 kg Maize flour (posho)')
        ->and(StoreMovement::sole()->finance_entry_id)->toBe($entry->id);
});

it('can receive without recording an expense, choosing the item in the page header', function () {
    Livewire::test(ManageStoreItems::class)
        ->callAction('receiveAny', data: ['store_item_id' => $this->posho->id, 'quantity' => 50, 'date' => '2026-10-24', 'record_expense' => false])
        ->assertHasNoActionErrors();

    expect((float) $this->posho->fresh()->quantity)->toBe(50.0)
        ->and(FinanceEntry::count())->toBe(0);
});

it('issues stock, never more than the store holds, and counts it', function () {
    app(StoreLedger::class)->receive($this->posho, 300, 2800, '2026-10-20');

    Livewire::test(ManageStoreItems::class)
        ->callTableAction('issue', $this->posho, data: ['quantity' => 120.5, 'date' => '2026-10-24', 'issued_to' => 'Kitchen', 'reference' => 'REQ-7'])
        ->assertHasNoTableActionErrors()
        ->callTableAction('issue', $this->posho, data: ['quantity' => 500, 'date' => '2026-10-24', 'issued_to' => 'Kitchen'])
        ->assertNotified('Not issued');

    expect((float) $this->posho->fresh()->quantity)->toBe(179.5)
        ->and($this->posho->fresh()->isLow())->toBeTrue();

    Livewire::test(ManageStoreItems::class)
        ->callTableAction('count', $this->posho, data: ['counted' => 175, 'date' => '2026-10-25', 'notes' => 'Spilt bag']);

    $count = StoreMovement::where('type', 'count')->sole();
    expect((float) $count->quantity)->toBe(-4.5)
        ->and((float) $count->balance_after)->toBe(175.0)
        ->and(StoreMovement::where('type', 'out')->sole()->issued_to)->toBe('Kitchen');

    Livewire::test(ListStoreMovements::class)
        ->assertCanSeeTableRecords(StoreMovement::all())
        ->assertSee('Spilt bag');
});

it('warns about stores running low on the menu and in Insights', function () {
    app(StoreLedger::class)->receive($this->posho, 150, null, '2026-10-20');

    expect(StoreItemResource::getNavigationBadge())->toBe('1');

    $findings = collect((new SchoolInsights($this->school->id, CarbonImmutable::parse('2026-10-25')))->findings());
    expect($findings->firstWhere('key', 'stores-low')['title'])->toBe('1 store item running low');
});

it('keeps an asset register with tag numbers, places and condition', function () {
    Livewire::test(ManageAssets::class)
        ->callAction('create', data: ['name' => 'Student desk', 'category' => 'furniture', 'quantity' => 40, 'location' => 'S.1 East', 'condition' => 'good', 'cost' => 4000000])
        ->assertHasNoActionErrors()
        ->callAction('create', data: ['name' => 'HP laptop', 'category' => 'ict', 'quantity' => 1, 'location' => 'Bursar\'s office', 'condition' => 'new', 'serial_no' => '5CD1234'])
        ->assertHasNoActionErrors();

    $desks = Asset::where('name', 'Student desk')->sole();
    $laptop = Asset::where('name', 'HP laptop')->sole();
    expect([$desks->tag_no, $laptop->tag_no])->toBe(['AST-0001', 'AST-0002']);

    Livewire::test(ManageAssets::class)
        ->callTableBulkAction('condition', [$laptop], data: ['condition' => 'lost'])
        ->assertCanSeeTableRecords([$desks])
        ->assertCanNotSeeTableRecords([$laptop])
        ->assertSee('40 items in use');

    expect($laptop->fresh()->disposed_on?->toDateString())->toBe('2026-10-25');
});

it('opens the stores to the storekeeper and bursar, not to a teacher', function () {
    expect(StoreItemResource::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Bursar'));
    expect(AssetResource::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));
    expect(StoreItemResource::canAccess())->toBeFalse()
        ->and(AssetResource::canAccess())->toBeFalse();
});

it('only offers expense categories the school types in, never fees or salaries', function () {
    FinanceCategory::ensureDefaults($this->school->id);
    $salaries = FinanceCategory::where('school_id', $this->school->id)->where('system_key', 'payroll')->sole();

    expect(fn () => app(StoreLedger::class)->receive($this->posho, 10, 1000, '2026-10-24', expense: ['finance_category_id' => $salaries->id, 'method' => 'cash']))
        ->toThrow(ModelNotFoundException::class);

    expect((float) $this->posho->fresh()->quantity)->toBe(0.0);
});
