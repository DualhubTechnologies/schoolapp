<?php

namespace App\Filament\App\Widgets;

use App\Filament\Admin\Resources\DemoRequests\DemoRequestResource;
use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Models\DemoRequest;
use App\Models\School;
use App\Models\Student;
use App\Services\Subscriptions\SubscriptionManager;

/** Super Admin: the platform at a glance, linking into the admin panel. */
class PlatformKpis extends KpiCards
{
    public function cards(): array
    {
        $states = School::all()->map(fn (School $s) => SubscriptionManager::status($s)['state'])->countBy();
        $schools = (int) $states->sum();
        $newDemos = DemoRequest::where('status', 'new')->count();
        $newSchools = School::where('created_at', '>=', now()->subDays(30))->count();
        $schoolsUrl = SchoolResource::getUrl(panel: 'admin');

        return [
            static::card('blue', 'heroicon-o-building-office-2', 'Schools', number_format($schools),
                $newSchools ? "{$newSchools} joined in the last 30 days" : 'None joined in the last 30 days', $schoolsUrl,
                trend: $newSchools ? 'up' : null),
            static::card('emerald', 'heroicon-o-credit-card', 'Paying schools', number_format($states['active'] ?? 0),
                number_format($states['trial'] ?? 0) . ' on free trial', $schoolsUrl,
                static::percent($states['active'] ?? 0, $schools)),
            static::card($newDemos ? 'amber' : 'violet', 'heroicon-o-calendar-days', 'New demo requests', number_format($newDemos),
                $newDemos ? 'Waiting for a reply' : 'All followed up', DemoRequestResource::getUrl(panel: 'admin')),
            static::card('teal', 'heroicon-o-academic-cap', 'Learners on SchoolHub', number_format(Student::where('status', 'active')->count()),
                'Active, across all schools'),
        ];
    }
}
