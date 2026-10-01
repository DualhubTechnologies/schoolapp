<?php

namespace App\Console\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use Throwable;

/**
 * Downloads MaxMind's free GeoLite2 City database, which the Website
 * visitors page uses to show where visitors are. Needs MAXMIND_ACCOUNT_ID
 * and MAXMIND_LICENSE_KEY (a free MaxMind account) in .env. The new file
 * replaces the old one only once it has opened correctly, so a failed
 * download never leaves the site without a database.
 */
class GeoipUpdate extends Command
{
    protected $signature = 'geoip:update';

    protected $description = 'Download the GeoLite2 City database for visitor locations';

    protected const URL = 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz';

    public function handle(): int
    {
        $account = (string) config('services.maxmind.account_id');
        $key = (string) config('services.maxmind.license_key');
        $target = (string) config('services.maxmind.database');

        if ($account === '' || $key === '') {
            $this->error('Set MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY in .env first (free account at maxmind.com).');

            return self::FAILURE;
        }

        $work = storage_path('app/geoip/download-'.uniqid());
        File::ensureDirectoryExists($work);

        try {
            $archive = "{$work}/GeoLite2-City.tar.gz";

            $response = Http::withBasicAuth($account, $key)
                ->timeout(300)
                ->sink($archive)
                ->get(self::URL);

            if (! $response->successful()) {
                $this->error('MaxMind refused the download (HTTP '.$response->status().'). Check the account ID and licence key.');

                return self::FAILURE;
            }

            (new PharData($archive))->decompress();
            (new PharData("{$work}/GeoLite2-City.tar"))->extractTo($work);

            $found = collect(File::allFiles($work))->first(fn ($f) => $f->getExtension() === 'mmdb');

            if (! $found) {
                $this->error('The download did not contain a GeoLite2 City database.');

                return self::FAILURE;
            }

            // Open it before trusting it.
            (new Reader($found->getPathname()))->close();

            File::ensureDirectoryExists(dirname($target));
            File::copy($found->getPathname(), $target.'.new');
            File::move($target.'.new', $target);

            $this->info('GeoLite2 City database updated: '.$target);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not update the database: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($work);
        }
    }
}
