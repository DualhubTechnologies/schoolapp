<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Makes the pair of keys behind Windows app licences, once, on the online
 * server. The private key (signs licences) is written into .env and must
 * never leave the server. The public key (checks licences) is printed:
 * it goes into the Windows app's settings, and is not secret.
 *
 * Making a new pair later would invalidate every licence already issued,
 * so the command refuses unless --force is given.
 */
class LicenceKeygen extends Command
{
    protected $signature = 'licence:keygen {--force : Replace an existing key pair (every issued licence stops working)}';

    protected $description = 'Create the key pair that signs Windows app licences';

    public function handle(): int
    {
        if (filled(config('licence.private_key')) && ! $this->option('force')) {
            $this->error('A licence signing key already exists. Replacing it would stop every issued licence working; use --force only if you mean that.');
            $this->line('Public key: '.config('licence.public_key'));

            return self::FAILURE;
        }

        $pair = sodium_crypto_sign_keypair();
        $private = base64_encode(sodium_crypto_sign_secretkey($pair));
        $public = base64_encode(sodium_crypto_sign_publickey($pair));

        $env = $this->laravel->environmentFilePath();

        if (! File::exists($env)) {
            $this->error('No .env file found at '.$env.'.');

            return self::FAILURE;
        }

        $contents = File::get($env);

        foreach (['LICENCE_PRIVATE_KEY' => $private, 'LICENCE_PUBLIC_KEY' => $public] as $name => $value) {
            $line = $name.'='.$value;
            $contents = preg_match('/^'.$name.'=.*$/m', $contents)
                ? (string) preg_replace('/^'.$name.'=.*$/m', $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        File::put($env, $contents);

        $this->info('Licence keys created. The private key is in .env: keep it secret and backed up.');
        $this->newLine();
        $this->line('Public key (send this to be built into the Windows app):');
        $this->line($public);
        $this->newLine();
        $this->line('Now run: php artisan config:cache');

        return self::SUCCESS;
    }
}
