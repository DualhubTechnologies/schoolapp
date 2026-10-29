<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * The Windows app syncing with the school's online account in the
 * background: every few minutes, or straight after "Sync now" or linking
 * (routes/console.php, SyncClient::requestSync). Offline, or not linked:
 * does nothing and tries again next time. How it went shows on the top
 * bar's sync dot and the Sync page.
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
        } catch (LockTimeoutException) {
            $this->line('Already syncing.');

            return self::SUCCESS;
        } catch (ConnectionException) {
            $client->failed(SyncClient::OFFLINE);
            $this->line('Offline; trying again next time.');

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $client->failed(SyncClient::ERROR, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Received {$result['received']}, sent {$result['sent']}, {$result['review']} to decide on.");

        return self::SUCCESS;
    }
}
