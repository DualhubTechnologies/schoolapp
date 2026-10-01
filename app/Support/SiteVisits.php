<?php

namespace App\Support;

use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Records a visit to a public page for the Website visitors page: which
 * page, roughly where from (country and city), how the visitor arrived
 * (Google, WhatsApp...) and on what kind of device.
 *
 * Privacy: the IP address is used only to look up the place, on this
 * server, and is not stored. Unique visitors are counted with a one-way
 * code made from the address, browser and day, which cannot be turned
 * back into the address and changes every day.
 *
 * Not counted: search-engine and other bots, link previews, browser
 * prefetches, and the platform owner's own visits.
 */
class SiteVisits
{
    protected const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp\/|telegram|monitor|uptime|pingdom|lighthouse|headless|curl|wget|python|java\/|go-http|okhttp|axios|scrapy|httpclient|feedfetcher/i';

    public function __construct(protected VisitorLocation $location) {}

    /** Record after the page has been sent, so visitors never wait for it. */
    public function recordLater(Request $request, string $path): void
    {
        if (! $this->counts($request)) {
            return;
        }

        // defer(): once, after this response is sent (not the app's
        // terminating callbacks, which would run again on later requests
        // in the same process).
        defer(function () use ($request, $path): void {
            try {
                $this->record($request, $path);
            } catch (Throwable $e) {
                report($e);   // a lost count must never break the page
            }
        });
    }

    public function counts(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        if (! $request->isMethod('GET') || $agent === '' || preg_match(self::BOTS, $agent)) {
            return false;
        }

        // Pages the browser loads in advance, in case the link is clicked.
        if (in_array(strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prerender'], true)) {
            return false;
        }

        return ! (auth()->user()?->hasRole('Super Admin') ?? false);
    }

    public function record(Request $request, string $path): SiteVisit
    {
        $ip = $request->ip();
        $agent = (string) $request->userAgent();

        return SiteVisit::create([
            'visited_on' => today()->toDateString(),
            'path' => mb_substr($path, 0, 100),
            'visitor' => hash('sha256', today()->toDateString().'|'.$ip.'|'.$agent.'|'.config('app.key')),
            ...$this->location->lookup($ip),
            'source' => $this->source($request),
            'referrer_host' => $this->referrerHost($request),
            'device' => $this->device($agent),
        ]);
    }

    /** How the visitor arrived: a campaign tag (?utm_source=) first, then the referring site. */
    public function source(Request $request): string
    {
        $tagged = strtolower(trim((string) $request->query('utm_source')));

        if ($tagged !== '') {
            return $this->sourceFromHost($tagged);
        }

        $host = $this->referrerHost($request);

        if ($host === null) {
            return 'direct';
        }

        return $host === strtolower((string) $request->getHost()) ? 'site' : $this->sourceFromHost($host);
    }

    protected function sourceFromHost(string $host): string
    {
        return match (true) {
            (bool) preg_match('/(^|\.)google\.|^google$/', $host) => 'google',
            (bool) preg_match('/bing|yahoo|duckduckgo|yandex|ecosia|baidu|brave/', $host) => 'search',
            (bool) preg_match('/whatsapp|wa\.me/', $host) => 'whatsapp',
            (bool) preg_match('/facebook|fb\.com|fb\.me|^fb$/', $host) => 'facebook',
            str_contains($host, 'instagram') => 'instagram',
            (bool) preg_match('/(^|\.)t\.co$|twitter|(^|\.)x\.com$|^x$/', $host) => 'x',
            (bool) preg_match('/linkedin|lnkd\.in/', $host) => 'linkedin',
            (bool) preg_match('/youtube|youtu\.be/', $host) => 'youtube',
            default => 'other',
        };
    }

    protected function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_substr(strtolower($host), 0, 120) : null;
    }

    public function device(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|playbook|silk|(android(?!.*mobile))/i', $agent) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $agent) => 'mobile',
            default => 'desktop',
        };
    }
}
