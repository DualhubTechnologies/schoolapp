<?php

namespace App\Console\Commands;

use App\Services\Payments\MtnMomo;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Sandbox only: makes the MTN MoMo API user and key from the Collections
 * subscription key (MOMO_COLLECTION_KEY), and prints the .env lines.
 * Live keys come from MTN's partner portal instead.
 */
class MomoSandboxUser extends Command
{
    protected $signature = 'momo:sandbox-user {host? : Public host MTN may call back, e.g. www.schoolhubug.com}';

    protected $description = 'Create an MTN MoMo sandbox API user and key';

    public function handle(MtnMomo $momo): int
    {
        if (! MtnMomo::isSandbox()) {
            $this->error('This is for the sandbox only (MOMO_ENVIRONMENT=sandbox). Live keys come from MTN.');

            return self::FAILURE;
        }

        if (blank(config('services.momo.subscription_key'))) {
            $this->error('Set MOMO_COLLECTION_KEY first: the Collections primary key from momodeveloper.mtn.com → Profile.');

            return self::FAILURE;
        }

        $host = $this->argument('host') ?: (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        try {
            $keys = $momo->createSandboxUser($host);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sandbox user created. Add these to .env:');
        $this->line('MOMO_API_USER='.$keys['api_user']);
        $this->line('MOMO_API_KEY='.$keys['api_key']);

        return self::SUCCESS;
    }
}
