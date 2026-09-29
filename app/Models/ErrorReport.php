<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One distinct error (same exception class, file and line), however many
 * times it has happened. Recorded by App\Support\ErrorRecorder; read by the
 * platform owner under Error reports.
 *
 * @property int $id
 * @property string $fingerprint
 * @property string $exception_class
 * @property string $message
 * @property string $file
 * @property int $line
 * @property string|null $trace
 * @property int $occurrences
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property string|null $last_url
 * @property int|null $last_school_id
 * @property int|null $last_user_id
 * @property Carbon|null $resolved_at
 */
class ErrorReport extends Model
{
    protected $fillable = [
        'fingerprint',
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'last_url',
        'last_school_id',
        'last_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ErrorOccurrence, $this>
     */
    public function occurrenceLog(): HasMany
    {
        return $this->hasMany(ErrorOccurrence::class)->latest('created_at');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function lastSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'last_school_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /** The file path from the app root, e.g. "app/Services/IdCardService.php". */
    public function shortFile(): string
    {
        return ltrim(str_replace(base_path(), '', $this->file), '/');
    }
}
