<?php

namespace App\Jobs;

use App\Models\StudentImport;
use App\Services\StudentCsvImporter;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStudentImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public StudentImport $studentImport,
    ) {}

    public function handle(): void
    {
        try {
            Log::info('Student import started', [
                'import_id' => $this->studentImport->id,
            ]);

            $importer = new StudentCsvImporter($this->studentImport);

            $importer->import();

            $this->studentImport->refresh();

            $recipient = $this->studentImport->importedBy;

            if ($recipient) {
                $success = $this->studentImport->successful_rows;
                $failed = $this->studentImport->failed_rows;

                Notification::make()
                    ->title('Student import completed')
                    ->body(
                        "{$success} students imported" .
                        ($failed > 0 ? ", {$failed} rows skipped." : '.')
                    )
                    ->icon(
                        $failed > 0
                            ? 'heroicon-o-exclamation-triangle'
                            : 'heroicon-o-check-circle'
                    )
                    ->iconColor($failed > 0 ? 'warning' : 'success')
                    ->sendToDatabase($recipient);
            }

            Log::info('Student import completed', [
                'import_id' => $this->studentImport->id,
                'successful_rows' => $this->studentImport->successful_rows,
                'failed_rows' => $this->studentImport->failed_rows,
            ]);

        } catch (\Throwable $e) {

            $this->studentImport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $this->studentImport->appendLog(
                'Import failed: ' . $e->getMessage()
            );

            Log::error('Student import job failed', [
                'import_id' => $this->studentImport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $recipient = $this->studentImport->importedBy;

            if ($recipient) {
                Notification::make()
                    ->title('Student import failed')
                    ->body($e->getMessage())
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->sendToDatabase($recipient);
            }

            // Important: mark the job as failed rather than silently
            // treating the exception as successfully handled.
            throw $e;
        }
    }
}