<?php

use App\Filament\App\Resources\FeeStructures\Pages\CreateFeeStructure;
use App\Filament\App\Resources\FeeStructures\Pages\EditFeeStructure;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1', 'sequence' => 1, 'is_current' => true]);
    $this->s1 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1', 'level' => 1]);
    $this->s2 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2', 'level' => 2]);
    $this->day = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Day', 'is_active' => true]);
    $this->boarding = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Boarding', 'is_active' => true]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('sets Day and Boarding tuition for several classes in one go', function () {
    Livewire::test(CreateFeeStructure::class)
        ->fillForm([
            'name' => 'Tuition',
            'class_ids' => [$this->s1->id, $this->s2->id],
            'frequency' => 'per_term',
            'term_id' => $this->term->id,
            'applies_to' => 'all',
            'amount_for' => 'each',
            'residency_amounts' => [$this->day->id => '850,000', $this->boarding->id => '1,250,000'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $fees = FeeStructure::orderBy('school_class_id')->orderBy('residency_type_id')->get();

    expect($fees)->toHaveCount(4)
        ->and($fees->map(fn ($fee) => [$fee->schoolClass->name, $fee->residencyType->name, (float) $fee->amount])->all())->toBe([
            ['S.1', 'Day', 850000.0],
            ['S.1', 'Boarding', 1250000.0],
            ['S.2', 'Day', 850000.0],
            ['S.2', 'Boarding', 1250000.0],
        ]);
});

it('leaves out a residency whose amount is left empty', function () {
    Livewire::test(CreateFeeStructure::class)
        ->fillForm([
            'name' => 'Lunch', 'class_ids' => [$this->s1->id], 'frequency' => 'per_term', 'term_id' => $this->term->id,
            'amount_for' => 'each', 'residency_amounts' => [$this->day->id => '150,000'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FeeStructure::sole()->only(['residency_type_id', 'amount']))->toBe(['residency_type_id' => $this->day->id, 'amount' => '150000.00']);
});

it('makes an admission fee one for new learners, with no term', function () {
    Livewire::test(CreateFeeStructure::class)
        ->fillForm(['name' => 'Admission', 'class_ids' => [$this->s1->id], 'frequency' => 'once', 'amount' => '100,000'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FeeStructure::sole()->only(['frequency', 'applies_to', 'term_id', 'amount']))
        ->toBe(['frequency' => 'once', 'applies_to' => 'new_only', 'term_id' => null, 'amount' => '100000.00']);
});

it('keeps an edited fee consistent when it becomes an admission fee', function () {
    $fee = FeeStructure::create(['school_id' => $this->school->id, 'school_class_id' => $this->s1->id, 'term_id' => $this->term->id, 'name' => 'Development', 'frequency' => 'per_term', 'applies_to' => 'all', 'amount' => 150000, 'is_active' => true]);

    Livewire::test(EditFeeStructure::class, ['record' => $fee->getRouteKey()])
        ->fillForm(['frequency' => 'once'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($fee->fresh()->only(['frequency', 'applies_to', 'term_id']))->toBe(['frequency' => 'once', 'applies_to' => 'new_only', 'term_id' => null]);
});

it('fills in the settings when a common fee is tapped', function () {
    Livewire::test(CreateFeeStructure::class)
        ->fillForm(['common_fee' => 'Lunch'])
        ->assertFormSet(['name' => 'Lunch', 'frequency' => 'per_term', 'amount_for' => "only:{$this->day->id}"])
        ->fillForm(['common_fee' => 'Tuition'])
        ->assertFormSet(['name' => 'Tuition', 'amount_for' => 'each'])
        ->fillForm(['common_fee' => 'Admission'])
        ->assertFormSet(['name' => 'Admission', 'frequency' => 'once', 'amount_for' => 'all'])
        ->fillForm(['common_fee' => 'UNEB registration'])
        ->assertFormSet(['frequency' => 'on_demand']);
});

it('saves a fee for one residency only', function () {
    Livewire::test(CreateFeeStructure::class)
        ->fillForm([
            'name' => 'Lunch', 'class_ids' => [$this->s1->id, $this->s2->id], 'frequency' => 'per_term', 'term_id' => $this->term->id,
            'amount_for' => "only:{$this->day->id}", 'amount' => '150,000',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FeeStructure::pluck('residency_type_id')->unique()->all())->toBe([$this->day->id])
        ->and(FeeStructure::count())->toBe(2);
});

it('previews the fees it will add and the ones already set', function () {
    FeeStructure::create(['school_id' => $this->school->id, 'school_class_id' => $this->s1->id, 'term_id' => $this->term->id, 'name' => 'Tuition', 'frequency' => 'per_term', 'applies_to' => 'all', 'residency_type_id' => $this->day->id, 'amount' => 800000, 'is_active' => true]);

    Livewire::test(CreateFeeStructure::class)
        ->fillForm([
            'name' => 'Tuition', 'class_ids' => [$this->s1->id, $this->s2->id], 'frequency' => 'per_term', 'term_id' => $this->term->id,
            'amount_for' => 'each', 'residency_amounts' => [$this->day->id => '850,000', $this->boarding->id => '1,250,000'],
        ])
        ->assertSee('3 fees will be added.')
        ->assertSee('UGX 1,250,000')
        ->assertSee('Already set, will be skipped:')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FeeStructure::count())->toBe(4)
        ->and((float) FeeStructure::where('school_class_id', $this->s1->id)->where('residency_type_id', $this->day->id)->value('amount'))->toBe(800000.0);
});
