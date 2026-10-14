<?php

use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Resources\Students\StudentResource;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2']);
    Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('has one search box, in the top bar of every page', function () {
    foreach ([Dashboard::getUrl(), StudentResource::getUrl()] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect(substr_count($html, 'class="fi-global-search"'))->toBe(1)
            ->and($html)->toContain('class="sh-topbar-search"')
            ->and($html)->toContain('Search learners, parents, staff, receipts');
    }
});

it('finds a learner once, with their payment and account links', function () {
    $results = Filament::getGlobalSearchProvider()->getResults('Aisha');

    expect($results->getCategories()->keys()->all())->toBe(['students'])
        ->and($results->getCategories()['students']->first()->title)->toContain('Aisha Nakato');
});
