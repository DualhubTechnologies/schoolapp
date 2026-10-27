<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * The Windows app syncing with the school's online account on its own,
 * every few minutes (routes/console.php). Offline, or not linked: does
 * nothing and tries again next time.
 */
class SyncRun extends Command
{
    protected $signature = 'sync:run';

    protected $description = 'Sync the Windows app with the school\'s online account';

    public function handle(SyncClient $client): int
    {
        if (! $client->isLinked()) {
            $this->line('Not linked to an online account.');

            return self::SUCCESS;
        }

        try {
            $result = $client->run();
        } catch (ConnectionException) {
            $this->line('Offline; trying again next time.');

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Received {$result['received']}, sent {$result['sent']}, {$result['review']} to decide on.");

        return self::SUCCESS;
    }
}
