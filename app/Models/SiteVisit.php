<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One visit to a public page. See App\Support\SiteVisits for how it is
 * recorded (no IP address is stored) and the Website visitors page.
 *
 * @property int $id
 * @property Carbon $visited_on
 * @property string $path
 * @property string $visitor
 * @property string|null $country_code
 * @property string|null $country
 * @property string|null $city
 * @property string $source
 * @property string|null $referrer_host
 * @property string $device
 */
class SiteVisit extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Where a visitor came from, as shown on the Website visitors page. */
    public const SOURCES = [
        'direct' => 'Typed the address or a bookmark',
        'site' => 'Another SchoolHub page',
        'google' => 'Google',
        'search' => 'Other search engines',
        'whatsapp' => 'WhatsApp',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'x' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'other' => 'Other websites',
    ];

    public const DEVICES = ['mobile' => 'Phone', 'tablet' => 'Tablet', 'desktop' => 'Computer'];

    protected $fillable = [
        'visited_on',
        'path',
        'visitor',
        'country_code',
        'country',
        'city',
        'source',
        'referrer_host',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'visited_on' => 'date',
        ];
    }

    /**
     * Visits are kept for two years, enough to compare a term with the
     * same term last year.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('visited_on', '<', now()->subYears(2)->toDateString());
    }
}
