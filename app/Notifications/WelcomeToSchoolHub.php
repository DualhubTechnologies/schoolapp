<?php

namespace App\Notifications;

use App\Models\School;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the person who just registered a school: welcome, what they have, and where to start. */
class WelcomeToSchoolHub extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public School $school, public CarbonInterface $trialEndsOn)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Welcome to SchoolHub, {$this->school->name}!")
            ->greeting("Welcome, {$notifiable->name}!")
            ->line("Thank you for registering **{$this->school->name}** on SchoolHub. Your account is ready and you are the school administrator.")
            ->line("Your free trial runs until **{$this->trialEndsOn->format('j F Y')}**, with every module included: students, fees, exams and report cards, payroll and finance.")
            ->line('**Getting started**')
            ->line('1. Sign in and follow the setup checklist on your dashboard.')
            ->line('2. Set the current term, then add your classes and fee structure.')
            ->line('3. Import your learners from Excel.')
            ->line('4. Create logins for your bursar, teachers and director of studies.')
            ->action('Sign in to SchoolHub', url('/login'))
            ->line("You sign in with this email address: {$notifiable->email}. Your school code is **{$this->school->unique_code}**.")
            ->line('Need help? WhatsApp or call us on 0782 863209, or reply to this email.')
            ->salutation("Regards,\nThe SchoolHub team");
    }
}
