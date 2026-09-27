<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the platform owner: a school has registered itself and started its free trial. */
class SchoolRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public School $school) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->school;

        return (new MailMessage)
            ->subject("New school registration: {$s->name}")
            ->greeting('A new school has joined')
            ->line("**{$s->name}** — {$s->typeLabel()}, {$s->city}. Its free trial has started.")
            ->line("Administrator: {$s->contact_person}, {$s->phone}, {$s->email}.")
            ->action('View in SchoolHub', url('/admin/schools'));
    }
}
