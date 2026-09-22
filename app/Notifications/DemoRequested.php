<?php

namespace App\Notifications;

use App\Models\DemoRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the SchoolHub team: someone booked a demo on the landing page. */
class DemoRequested extends Notification
{
    public function __construct(public DemoRequest $demo)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->demo;

        $mail = (new MailMessage)
            ->subject("Demo request: {$d->school_name}")
            ->greeting('New demo request')
            ->line("**{$d->name}** from **{$d->school_name}**")
            ->line("Phone: {$d->phone}" . ($d->email ? " · Email: {$d->email}" : ''))
            ->line('Prefers: ' . (DemoRequest::CONTACT_METHODS[$d->preferred_contact] ?? $d->preferred_contact)
                . ($d->preferred_date ? ' · Preferred date: ' . $d->preferred_date->format('D j M Y') : ''))
            ->when($d->learners, fn ($m) => $m->line('Learners: ' . (DemoRequest::LEARNERS[$d->learners] ?? $d->learners)))
            ->when($d->message, fn ($m) => $m->line('Message: ' . $d->message))
            ->action('Reply on WhatsApp', $d->whatsappUrl());

        return $d->email ? $mail->replyTo($d->email, $d->name) : $mail;
    }
}
