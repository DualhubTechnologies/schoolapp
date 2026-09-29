<?php

namespace App\Support;

use App\Models\ErrorOccurrence;
use App\Models\ErrorReport;
use App\Notifications\ErrorReported;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Records every unexpected error (the ones Laravel reports -- not a
 * missing page, a validation message or a refused permission) so the
 * platform owner can see it under Error reports, and gives it a short
 * reference the person it happened to can quote, e.g. "E-7K3Q9P".
 *
 * The same error (exception class, file and line) is one report with a
 * running count. The owner is emailed when an error is new, or comes back
 * after being marked resolved -- not every time it repeats.
 *
 * This runs while something has already gone wrong, possibly the database
 * itself, so it never throws: if recording fails, the error is still in
 * the log file as before.
 */
class ErrorRecorder
{
    /** The reference of the error recorded during this request, if any. */
    protected ?string $reference = null;

    protected bool $recording = false;

    public function record(Throwable $e): ?string
    {
        if ($this->recording) {
            return null;
        }

        $this->recording = true;

        try {
            return $this->reference = $this->store($e);
        } catch (Throwable) {
            return null;
        } finally {
            $this->recording = false;
        }
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    protected function store(Throwable $e): string
    {
        $user = auth()->user();
        $request = app()->runningInConsole() ? null : request();
        $url = $request ? Str::limit($request->fullUrl(), 2000, '') : 'artisan '.implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 3));
        $now = now();

        $report = ErrorReport::firstOrNew(['fingerprint' => $this->fingerprint($e)]);
        $isNew = ! $report->exists;
        $cameBack = $report->exists && $report->isResolved();

        $report->fill([
            'exception_class' => $e::class,
            'message' => Str::limit($e->getMessage() ?: '(no message)', 2000),
            'file' => Str::limit($e->getFile(), 490, ''),
            'line' => $e->getLine(),
            'trace' => Str::limit($e->getTraceAsString(), 12000),
            'occurrences' => $report->occurrences + 1,
            'first_seen_at' => $isNew ? $now : $report->first_seen_at,
            'last_seen_at' => $now,
            'last_url' => $url,
            'last_school_id' => $user?->school_id,
            'last_user_id' => $user?->getKey(),
            'resolved_at' => null,
        ])->save();

        $reference = $this->newReference();

        ErrorOccurrence::create([
            'error_report_id' => $report->getKey(),
            'reference' => $reference,
            'url' => $url,
            'method' => $request?->method(),
            'school_id' => $user?->school_id,
            'user_id' => $user?->getKey(),
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 490, '') : null,
        ]);

        if (($isNew || $cameBack) && filled(config('contact.email'))) {
            // After the response is sent, so a slow mail server never
            // delays the error page -- and a mail failure is only logged.
            defer(function () use ($report, $reference, $cameBack): void {
                try {
                    Notification::route('mail', config('contact.email'))->notify(new ErrorReported($report, $reference, $cameBack));
                } catch (Throwable $mailError) {
                    logger()->warning('Could not email the error report: '.$mailError->getMessage());
                }
            });
        }

        return $reference;
    }

    /**
     * The same error wherever and whenever it happens: its class and where
     * it was thrown. The message is left out, since it often carries a
     * record id or a value that differs each time.
     */
    protected function fingerprint(Throwable $e): string
    {
        return hash('sha256', $e::class.'|'.$e->getFile().'|'.$e->getLine());
    }

    /** "E-" and six letters or digits, leaving out ones easily misread (0/O, 1/I). */
    protected function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = 'E-';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (ErrorOccurrence::where('reference', $code)->exists());

        return $code;
    }
}
