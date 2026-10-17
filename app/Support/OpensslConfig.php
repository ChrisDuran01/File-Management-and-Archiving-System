<?php

namespace App\Support;

/**
 * PHP's OpenSSL needs an openssl.cnf to generate EC keys, which web push
 * does for every send. Apache under XAMPP sets OPENSSL_CONF, but the CLI
 * (artisan, queue:work) usually doesn't, and then openssl_pkey_new() fails
 * with "no such file".
 *
 * On Windows this can NOT be fixed with putenv() from inside PHP (OpenSSL
 * reads its own copy of the environment) - OPENSSL_CONF must be set in the
 * real environment before `php` starts, or the `config` option passed to
 * openssl_pkey_new() (which is what generateEcKeyPair() does).
 */
class OpensslConfig
{
    /** Where an openssl.cnf can be found, or null. */
    public static function path(): ?string
    {
        $candidates = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            env('WEBPUSH_OPENSSL_CONF'),
            dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf', // XAMPP / Windows PHP
            dirname(PHP_BINARY, 2) . '/apache/conf/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Whether the library's own openssl_pkey_new() calls will work here. */
    public static function ecKeysWork(): bool
    {
        $key = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        while (openssl_error_string() !== false) {
            // drain the error queue so a failed probe doesn't leak into later calls
        }

        return $key !== false;
    }

    /**
     * Generate a P-256 key pair using an explicit config file, so it works
     * even when OPENSSL_CONF isn't set.
     *
     * @return array{publicKey: string, privateKey: string}|null  base64url, VAPID format
     */
    public static function generateEcKeyPair(): ?array
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        if ($cnf = self::path()) {
            $options['config'] = $cnf;
        }

        $key = @openssl_pkey_new($options);
        if ($key === false) {
            return null;
        }

        $ec = openssl_pkey_get_details($key)['ec'] ?? null;
        if (! $ec) {
            return null;
        }

        $x = str_pad($ec['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
        $d = str_pad($ec['d'], 32, "\0", STR_PAD_LEFT);

        $b64 = fn (string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        return [
            'publicKey'  => $b64("\x04" . $x . $y), // uncompressed EC point
            'privateKey' => $b64($d),
        ];
    }
}
