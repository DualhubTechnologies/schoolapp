<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\ErrorReports\ErrorReportResource;
use App\Models\ErrorReport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * To the SchoolHub team: an error that has not been seen before, or one
 * marked resolved that has come back. Repeats of an open error are only
 * counted, not emailed (see App\Support\ErrorRecorder).
 */
class ErrorReported extends Notification
{
    public function __construct(public ErrorReport $report, public string $reference, public bool $cameBack = false) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->report;
        $school = $report->lastSchool?->name;

        return (new MailMessage)
            ->error()
            ->subject(($this->cameBack ? 'Error is back: ' : 'New error: ').Str::limit($report->message, 70))
            ->greeting($this->cameBack ? 'A resolved error has happened again' : 'A new error on SchoolHub')
            ->line('**'.class_basename($report->exception_class).'**: '.Str::limit($report->message, 300))
            ->line('Where: `'.$report->shortFile().':'.$report->line.'`')
            ->line('Page: '.($report->last_url ?? '—'))
            ->line('School: '.($school ?? '—').' · Reference: **'.$this->reference.'**')
            ->line('Repeats are counted on the report rather than emailed again.')
            ->action('Open the error report', ErrorReportResource::getUrl('view', ['record' => $report], panel: 'admin'));
    }
}
