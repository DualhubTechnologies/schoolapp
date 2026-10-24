<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A platform-wide setting kept in the database, encrypted with APP_KEY:
 * things set from the admin screens, such as the Google Drive connection
 * for off-site backups.
 *
 * @property string $key
 * @property string|null $value
 */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->first()->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function forget(string ...$keys): void
    {
        static::query()->whereKey($keys)->delete();
    }
}
