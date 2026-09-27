<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * The 6-digit code that confirms a new school administrator's email
 * (App\Support\EmailVerificationCode). Sent straight away rather than
 * queued: the person is waiting on the page for it.
 */
class ConfirmYourEmail extends Notification
{
    use Queueable;

    public function __construct(public string $code, public int $expiresAfterMinutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $school = $notifiable->school->name ?? 'your school';

        return (new MailMessage)
            ->subject("{$this->code} is your SchoolHub confirmation code")
            ->greeting("Hello {$notifiable->name},")
            ->line("Enter this code on SchoolHub to confirm your email address and finish registering **{$school}**:")
            ->line(new HtmlString(
                '<div style="margin:8px 0 20px;padding:16px;border:1px solid #dbe3ee;border-radius:10px;background:#f4f7fb;'
                .'text-align:center;font-size:32px;font-weight:700;letter-spacing:10px;color:#0d1f38;font-family:Menlo,Consolas,monospace;">'
                .e($this->code).'</div>'
            ))
            ->line("The code works for {$this->expiresAfterMinutes} minutes. Never share it with anyone — SchoolHub staff will never ask for it.")
            ->action('Open the confirmation page', route('filament.app.auth.verify-email'))
            ->line('If you did not register a school on SchoolHub, you can ignore this email.')
            ->salutation("Regards,\nThe SchoolHub team");
    }
}
