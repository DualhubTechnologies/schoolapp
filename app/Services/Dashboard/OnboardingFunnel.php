<?php

namespace App\Services\Dashboard;

use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Super Admin: how far each newly registered school has got, and where
 * schools stop. Each school is counted at every step it has passed; its
 * "stuck at" step is the first one it has not.
 */
class OnboardingFunnel
{
    public const STEPS = [
        'registered' => 'Registered',
        'confirmed' => 'Confirmed email',
        'signed_in' => 'Signed in',
        'classes' => 'Added classes',
        'learners' => 'Added learners',
        'fees' => 'Set fees',
        'paid' => 'Took a payment',
    ];

    /**
     * @return array{steps: array<int, array{key: string, label: string, count: int, percent: int}>, stuck: list<array{school: School, step: string, admin: ?User, days: int}>, total: int}
     */
    public function build(int $days = 90): array
    {
        $schools = School::where('created_at', '>=', now()->subDays($days))
            ->where(fn ($q) => $q->whereNull('setup_choice')->orWhere('setup_choice', '!=', 'existing'))
            ->latest()
            ->get();

        $ids = $schools->modelKeys();

        $admins = User::role('School Admin')->whereIn('school_id', $ids)->orderBy('id')->get()->unique('school_id')->keyBy('school_id');
        $usersBySchool = User::whereIn('school_id', $ids)->pluck('school_id', 'id');
        $signedIn = Activity::where('event', 'login')->where('causer_type', (new User)->getMorphClass())
            ->whereIn('causer_id', $usersBySchool->keys())->distinct()->pluck('causer_id')
            ->map(fn ($userId) => $usersBySchool[$userId])->unique()->flip();
        $withClasses = SchoolClass::whereIn('school_id', $ids)->distinct()->pluck('school_id')->flip();
        $withLearners = Student::whereIn('school_id', $ids)->distinct()->pluck('school_id')->flip();
        $withFees = FeeStructure::whereIn('school_id', $ids)->distinct()->pluck('school_id')->flip();
        $withPayments = StudentPayment::whereIn('school_id', $ids)->distinct()->pluck('school_id')->flip();

        $passed = [];
        $stuck = [];

        foreach ($schools as $school) {
            $admin = $admins->get($school->id);
            $done = [
                'registered' => true,
                'confirmed' => $admin === null || $admin->email_verification_code === null || $admin->email_verified_at !== null,
                'signed_in' => $signedIn->has($school->id),
                'classes' => $withClasses->has($school->id),
                'learners' => $withLearners->has($school->id),
                'fees' => $withFees->has($school->id),
                'paid' => $withPayments->has($school->id),
            ];

            $firstMissing = null;

            foreach (self::STEPS as $key => $label) {
                if ($firstMissing === null && $done[$key]) {
                    $passed[$key] = ($passed[$key] ?? 0) + 1;
                } elseif ($firstMissing === null) {
                    $firstMissing = $key;
                }
            }

            if ($firstMissing) {
                $stuck[] = [
                    'school' => $school,
                    'step' => self::STEPS[$firstMissing],
                    'admin' => $admin,
                    'days' => (int) $school->created_at->diffInDays(now()),
                ];
            }
        }

        $total = $schools->count();

        return [
            'steps' => array_map(fn (string $key) => [
                'key' => $key,
                'label' => self::STEPS[$key],
                'count' => $passed[$key] ?? 0,
                'percent' => $total ? (int) round(($passed[$key] ?? 0) / $total * 100) : 0,
            ], array_keys(self::STEPS)),
            'stuck' => array_slice($stuck, 0, 12),
            'total' => $total,
        ];
    }
}
