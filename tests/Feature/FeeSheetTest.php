<?php

use App\Filament\Pages\FeeStructureSheet;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Services\FeeSheet;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create([
        'name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary',
        'fee_payment_bank' => 'Centenary Bank, A/C 3100012345',
        'fee_sheet_notes' => "1 ream of paper\n4 toilet rolls",
    ]);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1', 'sequence' => 1, 'is_current' => true, 'start_date' => '2026-02-02', 'end_date' => '2026-05-01']);

    foreach (['S.1', 'S.2', 'S.3', 'S.4'] as $level => $name) {
        $this->classes[$name] = SchoolClass::create(['school_id' => $this->school->id, 'name' => $name, 'level' => $level + 1]);
    }

    $this->day = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Day', 'is_active' => true]);
    $this->boarding = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Boarding', 'is_active' => true]);

    $this->fee = function (string $class, array $data): FeeStructure {
        return FeeStructure::create($data + [
            'school_id' => $this->school->id, 'school_class_id' => $this->classes[$class]->id,
            'term_id' => $this->term->id, 'frequency' => 'per_term', 'applies_to' => 'all', 'is_active' => true,
        ]);
    };

    foreach (['S.1' => 850000, 'S.2' => 850000, 'S.3' => 850000, 'S.4' => 900000] as $class => $tuition) {
        ($this->fee)($class, ['name' => 'Tuition', 'residency_type_id' => $this->day->id, 'amount' => $tuition]);
        ($this->fee)($class, ['name' => 'Tuition', 'residency_type_id' => $this->boarding->id, 'amount' => $tuition + 400000]);
        ($this->fee)($class, ['name' => 'Medical', 'amount' => 20000]);
        ($this->fee)($class, ['name' => 'Admission', 'frequency' => 'once', 'applies_to' => 'new_only', 'term_id' => null, 'amount' => 100000]);
    }

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('prints one table for classes that pay the same, and keeps each class\'s own amounts', function () {
    $termly = (new FeeSheet($this->term))->termly();

    expect(collect($termly)->pluck('classes')->all())->toBe(['S.1 - S.3', 'S.4'])
        ->and($termly[0]['rows'][0])->toBe(['name' => 'Tuition', 'amounts' => [$this->day->id => 850000.0, $this->boarding->id => 1250000.0]])
        ->and($termly[1]['totals'])->toBe([$this->day->id => 920000.0, $this->boarding->id => 1320000.0]);
});

it('lists the admission fees apart, with what a new learner pays in the first term', function () {
    $sheet = new FeeSheet($this->term);

    expect(collect($sheet->admission())->pluck('classes')->all())->toBe(['S.1 - S.4'])
        ->and(collect($sheet->termly())->flatMap(fn ($group) => collect($group['rows'])->pluck('name'))->unique()->values()->all())->toBe(['Tuition', 'Medical'])
        ->and($sheet->firstTerm())->toBe([
            ['classes' => 'S.1 - S.3', 'totals' => [$this->day->id => 970000.0, $this->boarding->id => 1370000.0]],
            ['classes' => 'S.4', 'totals' => [$this->day->id => 1020000.0, $this->boarding->id => 1420000.0]],
        ]);
});

it('lists charges billed only where they apply', function () {
    ($this->fee)('S.4', ['name' => 'UNEB registration', 'frequency' => 'on_demand', 'term_id' => null, 'amount' => 164000]);

    expect((new FeeSheet($this->term))->other())->toBe([['name' => 'UNEB registration', 'detail' => 'S.4: 164,000']]);
});

it('names groups of classes the way a school writes them', function () {
    $sheet = new FeeSheet($this->term);
    $id = fn (string ...$names) => collect($names)->map(fn ($name) => $this->classes[$name]->id)->all();

    expect($sheet->classLabel($id('S.1', 'S.2', 'S.3')))->toBe('S.1 - S.3')
        ->and($sheet->classLabel($id('S.1', 'S.2')))->toBe('S.1 and S.2')
        ->and($sheet->classLabel($id('S.1', 'S.3', 'S.4')))->toBe('S.1, S.3 and S.4');
});

it('gives a school without day and boarding a single amount column', function () {
    ResidencyType::query()->update(['is_active' => false]);

    $sheet = new FeeSheet($this->term);

    expect($sheet->columns())->toBe([0 => 'Amount'])
        ->and($sheet->termly()[1]['rows'][1])->toBe(['name' => 'Medical', 'amounts' => [0 => 20000.0]]);
});

it('shows the term dates, how to pay and the school\'s requirements on the sheet', function () {
    Livewire::test(FeeStructureSheet::class)
        ->assertSee('Term begins Monday 2 February 2026 and ends Friday 1 May 2026')
        ->assertSee('S.1 - S.3')
        ->assertSee('On admission (new learners)')
        ->assertSee('Centenary Bank, A/C 3100012345')
        ->assertSee('4 toilet rolls')
        ->assertSee('Head teacher');
});
