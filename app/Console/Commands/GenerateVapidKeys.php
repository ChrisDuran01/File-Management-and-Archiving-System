<?php

namespace App\Console\Commands;

use App\Support\OpensslConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Minishlink\WebPush\VAPID;

/**
 * One-time setup for browser push: creates the VAPID key pair the browsers'
 * push services use to verify that pushes really come from this site, and
 * writes it into .env. Refuses to overwrite existing keys - replacing them
 * silently invalidates every student's existing subscription.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid {--force : Replace existing keys (all current subscriptions stop working)}';

    protected $description = 'Generate the VAPID key pair used for student browser push notifications';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->error('.env not found.');

            return self::FAILURE;
        }

        $env = File::get($envPath);

        if (preg_match('/^WEBPUSH_VAPID_PRIVATE_KEY=.+$/m', $env) && ! $this->option('force')) {
            $this->warn('VAPID keys already exist. Use --force to replace them (existing student subscriptions will stop working).');

            return self::FAILURE;
        }

        // Prefer the library; fall back to our own generator (explicit
        // openssl.cnf) for CLIs where OPENSSL_CONF isn't set (XAMPP/Windows).
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable) {
            $keys = OpensslConfig::generateEcKeyPair();
        }

        if (! $keys) {
            $this->error('Could not generate keys: PHP OpenSSL could not create an EC key (openssl.cnf not found).');
            $this->line('Set WEBPUSH_OPENSSL_CONF in .env to the full path of openssl.cnf and retry.');

            return self::FAILURE;
        }

        $env = $this->setEnv($env, 'WEBPUSH_VAPID_PUBLIC_KEY', $keys['publicKey']);
        $env = $this->setEnv($env, 'WEBPUSH_VAPID_PRIVATE_KEY', $keys['privateKey']);

        File::put($envPath, $env);

        $this->info('VAPID keys written to .env. Run `php artisan config:clear` if config is cached.');

        return self::SUCCESS;
    }

    private function setEnv(string $env, string $key, string $value): string
    {
        $line = "{$key}={$value}";

        if (preg_match("/^{$key}=.*$/m", $env)) {
            return preg_replace("/^{$key}=.*$/m", $line, $env);
        }

        return rtrim($env, "\r\n") . "\n{$line}\n";
    }
}
