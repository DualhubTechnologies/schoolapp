<?php

use App\Filament\App\Resources\Payments\PaymentResource;
use App\Filament\App\Resources\Students\StudentResource;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);

    $other = School::create(['name' => 'Other', 'slug' => 'other', 'email' => 'other@example.com', 'school_type' => 'primary']);
    Student::create(['school_id' => $other->id, 'name' => 'Aisha Other', 'admission_no' => 'ADM-001', 'status' => 'active']);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('finds a learner of this school by admission number, with shortcuts', function () {
    $results = StudentResource::getGlobalSearchResults('ADM-001');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Aisha Nakato (ADM-001)')
        ->and($results->first()->details)->toMatchArray(['Class' => 'P.4'])
        ->and(collect($results->first()->actions)->map->getLabel()->all())->toBe(['Receive payment', 'Fees account']);
});

it('finds a receipt by its number and opens the receipt', function () {
    $payment = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'amount' => 150000, 'paid_on' => today(), 'method' => 'cash']);

    $result = PaymentResource::getGlobalSearchResults($payment->receipt_no)->sole();

    expect($result->title)->toContain('UGX 150,000')
        ->and($result->url)->toBe(route('filament.app.fees.receipt', ['payment' => $payment->id]));
});
