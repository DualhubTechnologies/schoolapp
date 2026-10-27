<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Windows computer linked to a school's online account, to sync with it
 * (App\Services\Sync). Made with a one-hour link code; the computer then
 * holds a secret token, of which only a hash is kept here.
 *
 * @property int $id
 * @property int $school_id
 * @property string|null $name
 * @property string|null $link_code
 * @property Carbon|null $link_expires_at
 * @property string|null $token
 * @property string|null $machine the computer it is linked on (Machine)
 * @property string|null $created_by
 * @property Carbon|null $linked_at
 * @property Carbon|null $last_synced_at
 */
class SyncDevice extends Model
{
    protected $fillable = ['school_id', 'name', 'link_code', 'link_expires_at', 'token', 'machine', 'created_by', 'linked_at', 'last_synced_at'];

    protected $hidden = ['token', 'link_code'];

    protected function casts(): array
    {
        return [
            'link_expires_at' => 'datetime',
            'linked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isLinked(): bool
    {
        return $this->token !== null;
    }
}
