<?php

use App\Filament\Widgets\StudentsByClassChart;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

it('shows boys and girls side by side in each class, with the class total', function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');

    $school = School::create(['name' => 'Light Secondary', 'slug' => 'light', 'email' => 'light@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($school);
    $s1 = SchoolClass::create(['school_id' => $school->id, 'name' => 'S.1', 'level' => 1]);
    $s2 = SchoolClass::create(['school_id' => $school->id, 'name' => 'S.2', 'level' => 2]);

    $n = 0;
    foreach ([[$s1, 'male'], [$s1, 'Male'], [$s1, 'female'], [$s2, 'FEMALE'], [$s2, null]] as [$class, $gender]) {
        Student::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'first_name' => 'Learner', 'last_name' => (string) ++$n, 'admission_no' => "ADM-{$n}", 'gender' => $gender, 'status' => 'active']);
    }

    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));

    $chart = new StudentsByClassChart;
    $data = (fn () => $this->getData())->call($chart);
    $options = (fn () => $this->getOptions())->call($chart);
    $series = collect($data['datasets'])->mapWithKeys(fn ($d) => [$d['label'] => $d['data']]);

    expect($data['labels'])->toBe(['S.1 (3)', 'S.2 (2)'])
        ->and($series['Male'])->toBe([2, 0])
        ->and($series['Female'])->toBe([1, 1])
        ->and($series['Not recorded'])->toBe([0, 1])
        ->and($options['scales']['x']['stacked'])->toBeFalse();
});
