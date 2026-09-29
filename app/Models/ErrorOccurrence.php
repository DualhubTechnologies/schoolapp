<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One time an error happened, with the reference shown to the person it
 * happened to. Kept for 60 days; the report it belongs to keeps the count.
 *
 * @property int $id
 * @property int $error_report_id
 * @property string $reference
 * @property string|null $url
 * @property string|null $method
 * @property int|null $school_id
 * @property int|null $user_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class ErrorOccurrence extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'error_report_id',
        'reference',
        'url',
        'method',
        'school_id',
        'user_id',
        'ip',
        'user_agent',
    ];

    /**
     * @return BelongsTo<ErrorReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(ErrorReport::class, 'error_report_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(60));
    }
}
