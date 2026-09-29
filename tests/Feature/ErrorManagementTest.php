<?php

use App\Filament\Admin\Resources\ErrorReports\ErrorReportResource;
use App\Filament\Admin\Resources\ErrorReports\Pages\ListErrorReports;
use App\Filament\Admin\Resources\ErrorReports\Pages\ViewErrorReport;
use App\Models\ErrorOccurrence;
use App\Models\ErrorReport;
use App\Models\School;
use App\Models\User;
use App\Notifications\ErrorReported;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    $this->withoutDefer();
    Notification::fake();
    config(['app.debug' => false, 'contact.email' => 'team@schoolhub.test']);

    // The same line throws every time, so both requests are one error.
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('Boom from the test'));
});

it('records an unexpected error and shows the user a reference to quote', function () {
    $response = $this->get('/_test/boom');

    $occurrence = ErrorOccurrence::sole();

    $response->assertStatus(500)
        ->assertSee('Sorry, something went wrong on our side')
        ->assertSee($occurrence->reference)
        ->assertHeader('X-Error-Reference', $occurrence->reference);

    expect($occurrence->reference)->toMatch('/^E-[A-HJ-NP-Z2-9]{6}$/')
        ->and(ErrorReport::sole())
        ->message->toBe('Boom from the test')
        ->occurrences->toBe(1)
        ->resolved_at->toBeNull();

    Notification::assertSentOnDemand(ErrorReported::class, fn (ErrorReported $n): bool => $n->reference === $occurrence->reference && ! $n->cameBack);
});

it('counts repeats of an open error without emailing again', function () {
    $this->get('/_test/boom');
    $this->get('/_test/boom');

    expect(ErrorReport::sole()->occurrences)->toBe(2)
        ->and(ErrorOccurrence::count())->toBe(2);

    Notification::assertSentOnDemandTimes(ErrorReported::class, 1);
});

it('reopens a resolved error that comes back, and says so', function () {
    $this->get('/_test/boom');
    ErrorReport::sole()->update(['resolved_at' => now()]);

    $this->get('/_test/boom');

    expect(ErrorReport::sole()->resolved_at)->toBeNull();
    Notification::assertSentOnDemandTimes(ErrorReported::class, 2);
    Notification::assertSentOnDemand(ErrorReported::class, fn (ErrorReported $n): bool => $n->cameBack);
});

it('shows friendly pages for everyday problems, without recording them as errors', function () {
    $this->get('/no-such-page-here')
        ->assertNotFound()
        ->assertSee("We couldn't find that page")
        ->assertSee('Go to my dashboard');

    expect(ErrorReport::count())->toBe(0);
});

it('lets the platform owner find an error by the reference a user quotes', function () {
    $this->get('/_test/boom');
    $reference = ErrorOccurrence::sole()->reference;
    $report = ErrorReport::sole();

    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));
    Filament::setCurrentPanel('admin');

    Livewire::test(ListErrorReports::class)
        ->assertOk()
        ->searchTable($reference)
        ->assertCanSeeTableRecords([$report]);

    Livewire::test(ViewErrorReport::class, ['record' => $report->getRouteKey()])
        ->assertOk()
        ->assertSee('Boom from the test')
        ->assertSee($reference)
        ->callAction('resolve');

    expect($report->fresh()->isResolved())->toBeTrue();
});

it('keeps error reports from school users', function () {
    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($school);
    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));

    expect(ErrorReportResource::canViewAny())->toBeFalse();
});

it('words a failed upload plainly and keeps the other validation messages', function () {
    expect(__('validation.uploaded', ['attribute' => 'data.photo.8f3c']))
        ->toStartWith('This file could not be uploaded.')
        ->not->toContain('data.photo')
        ->and(__('validation.required', ['attribute' => 'name']))->toBe('The name field is required.');
});
