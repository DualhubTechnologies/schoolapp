<?php

namespace App\Services\Subscriptions;

use App\Models\School;
use App\Models\SubscriptionReminder;
use App\Models\User;
use App\Notifications\SubscriptionEnding;
use App\Services\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells schools, ahead of time, that their free trial or subscription is
 * ending, and again when it has ended and when the system locks.
 *
 * Works from SubscriptionManager::status(), the same rules as the banner
 * and the lock, so a reminder never disagrees with what the school sees.
 *
 * Each reminder is sent once per end date (subscription_reminders). If a
 * daily run is missed, the next run sends only the most urgent reminder
 * not yet sent — never a burst of stale ones.
 */
class SubscriptionReminders
{
    public function __construct(protected SmsSender $sms)
    {
    }

    /**
     * The reminder a school is due today, or null.
     *
     * @return array{kind: string, state: string, trial: bool, ends_on: CarbonImmutable, days_left: int, lock_on: CarbonImmutable, locks_in: int, sms: bool}|null
     */
    public static function due(School $school): ?array
    {
        $st = SubscriptionManager::status($school);

        if (! $st['ends_on'] || ! in_array($st['state'], ['trial', 'active', 'grace', 'expired'], true)) {
            return null;   // pending, rejected, suspended or never subscribed: nothing to remind about
        }

        $daysLeft = (int) $st['days_left'];
        $locksIn = (int) CarbonImmutable::today()->diffInDays($st['grace_ends_on'], false) + 1;

        $kind = match ($st['state']) {
            'trial', 'active' => static::beforeEnd($daysLeft),
            'grace' => $locksIn <= (int) config('subscriptions.lock_warning_days', 3) ? 'locks-soon' : 'ended',
            // Only on the first days of the lock; after that the school already knows.
            'expired' => $locksIn >= -6 ? 'locked' : null,
        };

        if (! $kind) {
            return null;
        }

        return [
            'kind' => $kind,
            'state' => $st['state'],
            // Still true after a trial runs out (grace, locked), so the wording stays "free trial".
            'trial' => (bool) $st['subscription']?->isTrial(),
            'ends_on' => $st['ends_on'],
            'days_left' => $daysLeft,
            'lock_on' => $st['grace_ends_on']->addDay(),
            'locks_in' => max(0, $locksIn),
            'sms' => $daysLeft <= (int) config('subscriptions.sms_within_days', 3),
        ];
    }

    /** The milestone the school has reached: 5 days left is the "7 days" reminder. */
    protected static function beforeEnd(int $daysLeft): ?string
    {
        $milestones = collect(config('subscriptions.reminder_days', [14, 7, 3, 1, 0]))->sort()->values();
        $reached = $milestones->first(fn (int $d) => $daysLeft <= $d);

        return match (true) {
            $reached === null => null,
            $reached === 0 => 'ends-today',
            default => "ends-in-{$reached}",
        };
    }

    public static function alreadySent(School $school, array $due): bool
    {
        return SubscriptionReminder::where('school_id', $school->getKey())
            ->whereDate('ends_on', $due['ends_on'])
            ->where('kind', $due['kind'])
            ->exists();
    }

    /**
     * Send the reminder: email to the school's administrators (or the
     * school's own address if it has none), SMS to the school's phone for
     * the urgent ones. Returns what was sent.
     *
     * @return array{emails: int, sms: bool, note: ?string}
     */
    public function send(School $school, array $due): array
    {
        $admins = User::role('School Admin')->where('school_id', $school->getKey())->get();
        $notification = new SubscriptionEnding($school, $due);

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification);
        } elseif ($school->email) {
            Notification::route('mail', $school->email)->notify($notification);
        }

        $emails = $admins->count() ?: ($school->email ? 1 : 0);
        $sms = false;
        $note = null;

        if ($due['sms'] && ! $school->phone) {
            $note = 'No school phone number, so no SMS';
        } elseif ($due['sms']) {
            $result = $this->sms->send($school->phone, SubscriptionEnding::smsText($school, $due));
            $sms = $result['ok'];
            $note = $result['ok'] ? null : 'SMS failed: ' . $result['error'];
        }

        SubscriptionReminder::create([
            'school_id' => $school->getKey(),
            'ends_on' => $due['ends_on']->toDateString(),
            'kind' => $due['kind'],
            'emails' => $emails,
            'sms' => $sms,
            'note' => $note,
        ]);

        return compact('emails', 'sms', 'note');
    }
}
