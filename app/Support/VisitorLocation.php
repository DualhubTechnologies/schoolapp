<?php

namespace App\Support;

use GeoIp2\Database\Reader;
use Illuminate\Container\Attributes\Scoped;
use Throwable;

/**
 * Country and city for an IP address, from MaxMind's GeoLite2 City
 * database on this server (config services.maxmind). Nothing is sent
 * anywhere. Without the database, or for a private or unknown address,
 * the answer is empty rather than a guess. One per request (#[Scoped]),
 * so the database file is opened once.
 */
#[Scoped]
class VisitorLocation
{
    protected ?Reader $reader = null;

    protected bool $opened = false;

    public function database(): string
    {
        return (string) config('services.maxmind.database');
    }

    public function isAvailable(): bool
    {
        return is_file($this->database());
    }

    /**
     * @return array{country_code: string|null, country: string|null, city: string|null}
     */
    public function lookup(?string $ip): array
    {
        $none = ['country_code' => null, 'country' => null, 'city' => null];

        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $none;
        }

        $reader = $this->reader();

        if (! $reader) {
            return $none;
        }

        try {
            $place = $reader->city($ip);

            return [
                'country_code' => $place->country->isoCode,
                'country' => $place->country->name,
                'city' => $place->city->name,
            ];
        } catch (Throwable) {
            return $none;
        }
    }

    /** The database, opened once per request; null if it is missing or unreadable. */
    protected function reader(): ?Reader
    {
        if (! $this->opened) {
            $this->opened = true;

            try {
                $this->reader = $this->isAvailable() ? new Reader($this->database()) : null;
            } catch (Throwable) {
                $this->reader = null;
            }
        }

        return $this->reader;
    }
}
