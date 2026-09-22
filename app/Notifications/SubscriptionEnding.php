<?php

namespace App\Notifications;

use App\Filament\Pages\SchoolSubscription;
use App\Models\School;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a school's administrators: the trial or subscription is ending, has
 * ended, or the system has locked. Sent by SubscriptionReminders from the
 * daily scheduled run, so it is sent straight away rather than queued.
 */
class SubscriptionEnding extends Notification
{
    /** @param  array{kind: string, state: string, ends_on: \Carbon\CarbonImmutable, days_left: int, lock_on: \Carbon\CarbonImmutable, locks_in: int}  $due */
    public function __construct(public School $school, public array $due)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->due;
        $school = $this->school->name;
        $trial = $d['trial'];
        $what = $trial ? 'free trial' : 'subscription';
        $ends = $d['ends_on']->format('l, j F Y');
        $lock = $d['lock_on']->format('l, j F Y');
        $days = fn (int $n) => $n . ' ' . str('day')->plural($n);

        $mail = (new MailMessage)->greeting('Hello' . (isset($notifiable->name) ? ' ' . $notifiable->name : '') . ',');

        $mail = match ($d['kind']) {
            'ended' => $mail
                ->subject("Your SchoolHub {$what} has ended — {$school}")
                ->line("The SchoolHub {$what} for **{$school}** ended on {$ends}.")
                ->line("Everything keeps working for now, but the system will lock on **{$lock}** unless " . ($trial ? 'you choose a plan.' : 'it is renewed.')),
            'locks-soon' => $mail
                ->subject("{$school} will be locked in {$days($d['locks_in'])}")
                ->line("The SchoolHub {$what} for **{$school}** ended on {$ends}.")
                ->line("The system will lock on **{$lock}**. Staff will not be able to receive fees, enter marks or run payroll until " . ($trial ? 'a plan is paid for.' : 'it is renewed.')),
            'locked' => $mail
                ->subject("SchoolHub is locked for {$school}")
                ->line($trial
                    ? "The free trial for **{$school}** has ended and no plan was chosen, so the system is now locked."
                    : "The SchoolHub subscription for **{$school}** has not been renewed, so the system is now locked.")
                ->line('Your data is safe and nothing has been deleted. Everything unlocks as soon as payment is recorded.'),
            'ends-today' => $mail
                ->subject("Your SchoolHub {$what} ends today")
                ->line("The {$what} for **{$school}** ends today, {$ends}.")
                ->line($trial
                    ? 'Choose a plan to keep using SchoolHub. Your data stays exactly as it is.'
                    : 'Renew now to avoid any interruption.'),
            default => $mail
                ->subject("Your SchoolHub {$what} ends in {$days($d['days_left'])}")
                ->line("The {$what} for **{$school}** ends on **{$ends}** — {$days($d['days_left'])} from today.")
                ->line($trial
                    ? 'To keep using SchoolHub, choose a plan before then. All your students, fees and marks stay exactly as they are.'
                    : 'Renew before then to keep everything running without interruption.'),
        };

        foreach (static::paymentLines() as $line) {
            $mail->line($line);
        }

        return $mail
            ->action($trial ? 'Choose a plan' : 'Renew subscription', SchoolSubscription::getUrl(panel: 'app'))
            ->line('Questions? Call or WhatsApp ' . config('contact.phone') . ' or email ' . config('contact.email') . '.');
    }

    /** Short enough for one or two SMS. */
    public static function smsText(School $school, array $due): string
    {
        $name = str($school->name)->limit(40, '');
        $what = $due['trial'] ? 'free trial' : 'subscription';
        $act = $due['trial'] ? 'Choose a plan' : 'Renew';
        $contact = config('contact.phone');

        return match ($due['kind']) {
            'ended' => "SchoolHub: {$name} {$what} has ended. It will lock on {$due['lock_on']->format('j M')}. {$act} to keep using it. Call {$contact}.",
            'locks-soon' => "SchoolHub: {$name} will be LOCKED on {$due['lock_on']->format('j M')}. {$act} to keep receiving fees and entering marks. Call {$contact}.",
            'locked' => "SchoolHub: {$name} is now locked. Your data is safe. Pay to unlock. Call {$contact}.",
            'ends-today' => "SchoolHub: {$name} {$what} ends TODAY. {$act} to avoid interruption. Call {$contact}.",
            default => "SchoolHub: {$name} {$what} ends on {$due['ends_on']->format('j M')} ({$due['days_left']} " . str('day')->plural($due['days_left']) . "). {$act} to keep using SchoolHub. Call {$contact}.",
        };
    }

    /** @return list<string> how to pay, from config/subscriptions.php */
    protected static function paymentLines(): array
    {
        $pay = config('subscriptions.payment', []);

        $ways = array_filter([
            filled($pay['mobile_money'] ?? null) ? 'MTN Mobile Money: ' . $pay['mobile_money'] : null,
            filled($pay['airtel_money'] ?? null) ? 'Airtel Money: ' . $pay['airtel_money'] : null,
            filled($pay['bank'] ?? null) ? 'Bank: ' . $pay['bank'] : null,
        ]);

        return $ways ? ['**How to pay:** ' . implode(' · ', $ways)] : [];
    }
}
