<?php

namespace App\Filament\App\Widgets;

use App\Filament\Admin\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Admin\Resources\OnlineUsers\OnlineUserResource;
use App\Models\User;
use App\Models\UserSession;
use Spatie\Activitylog\Models\Activity;

/** Super Admin: who is using SchoolHub today, under the platform cards. */
class PlatformActivityKpis extends KpiCards
{
    protected static ?int $sort = -4;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public function cards(): array
    {
        $onlineUrl = OnlineUserResource::getUrl(panel: 'admin');
        $logsUrl = ActivityLogResource::getUrl(panel: 'admin');
        $today = today()->startOfDay();

        $signedInToday = Activity::where('event', 'login')->where('created_at', '>=', $today);
        $failed = Activity::where('event', 'login_failed')->where('created_at', '>=', now()->subDay())->count();
        $changes = Activity::whereIn('event', ['created', 'updated', 'deleted'])->where('created_at', '>=', $today)->count();

        $usersToday = (clone $signedInToday)->distinct()->count('causer_id');
        $schoolsToday = User::whereIn('id', (clone $signedInToday)->select('causer_id'))->whereNotNull('school_id')->distinct()->count('school_id');

        return [
            $this->onlineCard($onlineUrl),
            static::card('blue', 'heroicon-o-arrow-right-end-on-rectangle', 'Signed in today', number_format($usersToday),
                $schoolsToday === 1 ? 'From 1 school' : 'From '.number_format($schoolsToday).' schools', $logsUrl),
            static::card($failed ? 'rose' : 'violet', 'heroicon-o-shield-exclamation', 'Failed sign-ins', number_format($failed),
                $failed ? 'In the last 24 hours — check the logs' : 'None in the last 24 hours', $logsUrl),
            static::card('amber', 'heroicon-o-pencil-square', 'Changes today', number_format($changes),
                'Records added, edited or deleted', $logsUrl),
        ];
    }

    /**
     * People active in the last few minutes. Needs the database session
     * driver; without it there is nothing to count.
     *
     * @return array{tone: string, icon: string, label: string, value: string, sub: ?string, url: ?string, progress: ?int, trend: 'up'|'down'|null}
     */
    protected function onlineCard(string $url): array
    {
        if (config('session.driver') !== 'database') {
            return static::card('emerald', 'heroicon-o-signal', 'Online now', '—', 'Needs SESSION_DRIVER=database', $url);
        }

        $active = UserSession::activeNow()->distinct()->count('user_id');
        $signedIn = UserSession::signedIn()->distinct()->count('user_id');

        return static::card('emerald', 'heroicon-o-signal', 'Online now', number_format($active),
            number_format($signedIn).' signed in, active in the last '.UserSession::ACTIVE_MINUTES.' min', $url,
            static::percent($active, $signedIn));
    }
}
