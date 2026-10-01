<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * On SQLite (the Windows app), saves date-only attributes as "2026-10-01".
 *
 * Eloquent writes every date cast in the connection's full format,
 * "2026-10-01 00:00:00". MySQL's DATE columns drop the time, so on the
 * server lookups by day just work. SQLite keeps the text as written, so
 * where('date', '2026-10-01') would find nothing (a register corrected
 * later the same day became a second register) and a range ending
 * '2026-10-31' would leave the last day out. Storing the plain date, as
 * MySQL does, keeps both databases answering the same way.
 *
 * Only date casts (date, immutable_date, with or without a format) are
 * touched; datetimes keep their time.
 */
class SqliteDates
{
    public static function register(): void
    {
        Event::listen('eloquent.saving: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;

            if ($model instanceof Model && $model->getConnection()->getDriverName() === 'sqlite') {
                static::normalise($model);
            }
        });
    }

    public static function normalise(Model $model): void
    {
        $attributes = $model->getAttributes();
        $changed = false;

        foreach ($model->getCasts() as $key => $cast) {
            $type = strtolower(explode(':', (string) $cast, 2)[0]);

            if (! in_array($type, ['date', 'immutable_date'], true) || ! isset($attributes[$key])) {
                continue;
            }

            $value = $attributes[$key];

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                continue;
            }

            $attributes[$key] = Carbon::parse($value)->format('Y-m-d');
            $changed = true;
        }

        if ($changed) {
            $model->setRawAttributes($attributes);
        }
    }
}
