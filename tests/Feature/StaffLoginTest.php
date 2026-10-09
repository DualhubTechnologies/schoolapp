<?php

use App\Filament\App\Resources\Expenses\ExpenseResource;
use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\App\Resources\Staff\Pages\ListStaff;
use App\Filament\App\Resources\Users\Pages\CreateUser;
use App\Filament\App\Resources\Users\UserResource;
use App\Filament\Pages\EnterMarks;
use App\Filament\Pages\ReceivePayment;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Modules;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('gives a teacher a login and goes on to choose their subjects', function () {
    $staff = Staff::create(['school_id' => $this->school->id, 'name' => 'Okello James', 'staff_no' => 'T001', 'position' => 'Teacher', 'category' => 'teaching', 'employment_date' => '2020-01-06', 'status' => 'active']);

    Livewire::test(ListStaff::class)
        ->callTableAction('createLogin', $staff, data: [
            'email' => 'okello@example.com',
            'password' => 'Secret-Pass-2026',
            'passwordConfirmation' => 'Secret-Pass-2026',
            'roles' => ['Teacher'],
        ])
        ->assertHasNoTableActionErrors()
        ->assertRedirect(UserResource::getUrl('edit', ['record' => User::where('email', 'okello@example.com')->first()]));

    expect($staff->fresh()->user?->hasRole('Teacher'))->toBeTrue();
});

it('starts chosen modules from the role\'s defaults, so adding one keeps the rest', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['roles' => [Role::findByName('Teacher')->id]])
        ->fillForm(['custom_access' => true])
        ->assertFormSet(['modules' => ['exams', 'students', 'attendance']]);
});

it('has a bursar role for fees and spending, without payroll or marks', function () {
    $bursar = User::factory()->create(['school_id' => $this->school->id])->assignRole('Bursar');
    $this->actingAs($bursar);

    expect(ReceivePayment::canAccess())->toBeTrue()
        ->and(ExpenseResource::canAccess())->toBeTrue()
        ->and(PayrollPeriodResource::canAccess())->toBeFalse()
        ->and(EnterMarks::canAccess())->toBeFalse()
        ->and(UserResource::canAccess())->toBeFalse();

    $this->get(route('filament.app.pages.dashboard'))->assertOk()->assertSee('Receive payment');
});

it('offers the head first, then the bursar and accountant', function () {
    $offered = collect(Role::all())->sortBy(fn (Role $role) => Modules::rolePosition($role->name))->pluck('name')->values()->all();

    expect(array_slice($offered, 0, 3))->toBe(['School Admin', 'Bursar', 'Accountant']);
});
