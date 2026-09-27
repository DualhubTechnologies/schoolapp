<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentImport extends Model
{
    protected $fillable = [
        'school_id',
        'imported_by',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'duplicate_rows',
        'column_map',
        'confirm_on_import',
        'validation_errors',
        'import_log',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'validation_errors' => 'array',
            'import_log' => 'array',
            'confirm_on_import' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'pending' => 'Pending',
        'validating' => 'Validating',
        'validated' => 'Validated',
        'importing' => 'Importing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    public function getElapsedSecondsAttribute(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();

        return (int) $this->started_at->diffInSeconds($end);
    }

    public function getEstimatedRemainingAttribute(): ?int
    {
        if (! $this->started_at || $this->processed_rows === 0) {
            return null;
        }

        $elapsed = $this->elapsed_seconds;
        $rate = $this->processed_rows / max($elapsed, 1);
        $remaining = $this->total_rows - $this->processed_rows;

        return (int) ceil($remaining / max($rate, 0.1));
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['validating', 'importing']);
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }

    public function appendLog(string $message): void
    {
        $log = $this->import_log ?? [];
        $log[] = [
            'time' => now()->format('H:i:s'),
            'message' => $message,
        ];
        $this->update(['import_log' => $log]);
    }
}
