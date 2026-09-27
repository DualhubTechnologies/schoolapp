<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Subscriptions\SubscriptionReminders;
use Illuminate\Console\Command;

/**
 * Daily: remind schools whose trial or subscription is ending, has ended,
 * or has locked. Scheduled in routes/console.php.
 */
class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:remind
        {--dry-run : Show who would be reminded, without sending anything}
        {--school= : Only this school (id)}';

    protected $description = 'Email (and for urgent ones, SMS) schools whose trial or subscription is ending';

    public function handle(SubscriptionReminders $reminders): int
    {
        $dry = (bool) $this->option('dry-run');
        $rows = [];

        School::query()
            ->where('status', 'active')   // suspended, pending and rejected schools are not reminded
            ->when($this->option('school'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('name')
            ->each(function (School $school) use ($reminders, $dry, &$rows) {
                $due = SubscriptionReminders::due($school);

                if (! $due) {
                    return;
                }

                if (SubscriptionReminders::alreadySent($school, $due)) {
                    $rows[] = [$school->name, $due['kind'], $due['ends_on']->format('j M Y'), 'already sent'];

                    return;
                }

                if ($dry) {
                    $rows[] = [$school->name, $due['kind'], $due['ends_on']->format('j M Y'), 'would send'.($due['sms'] ? ' + SMS' : '')];

                    return;
                }

                try {
                    $sent = $reminders->send($school, $due);
                    $rows[] = [$school->name, $due['kind'], $due['ends_on']->format('j M Y'),
                        "{$sent['emails']} email(s)".($sent['sms'] ? ' + SMS' : '').($sent['note'] ? " ({$sent['note']})" : '')];
                } catch (\Throwable $e) {
                    // One school's mail problem must not stop the others.
                    report($e);
                    $rows[] = [$school->name, $due['kind'], $due['ends_on']->format('j M Y'), 'FAILED: '.$e->getMessage()];
                }
            });

        if ($rows === []) {
            $this->info('No schools need a reminder today.');

            return self::SUCCESS;
        }

        $this->table(['School', 'Reminder', 'Ends on', $dry ? 'Would do' : 'Result'], $rows);

        return self::SUCCESS;
    }
}
