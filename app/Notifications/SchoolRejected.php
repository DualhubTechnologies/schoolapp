<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the school's administrator: their registration was not approved. */
class SchoolRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public School $school) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contact = config('subscriptions.payment.contact_phone') ?: config('subscriptions.payment.contact_email');

        return (new MailMessage)
            ->subject("Your SchoolHub registration for {$this->school->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("We could not approve the registration for **{$this->school->name}**.")
            ->when($this->school->rejection_reason, fn ($m) => $m->line('Reason: '.$this->school->rejection_reason))
            ->line($contact ? "If you think this is a mistake, contact us on {$contact}." : 'If you think this is a mistake, reply to this email.');
    }
}
