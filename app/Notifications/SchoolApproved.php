<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the school's administrator: their registration was approved. */
class SchoolApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public School $school, public int $trialDays)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->school->name} is ready on SchoolHub")
            ->greeting("Welcome, {$notifiable->name}!")
            ->line("Your registration for **{$this->school->name}** has been approved.")
            ->line("Your {$this->trialDays}-day free trial has started, with every module included.")
            ->line('Start by adding your classes, then import your learners and set up fees.')
            ->action('Sign in to SchoolHub', url('/login'));
    }
}
