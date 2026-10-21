<?php

// Used by run-service.cmd: returns once MySQL accepts connections on the
// host/port in .env. Only checks that the port is open - a wrong password
// etc. is left for artisan itself to report.

require __DIR__.'/../vendor/autoload.php';

$env = Dotenv\Dotenv::createArrayBacked(dirname(__DIR__))->safeLoad();

if (! in_array($env['DB_CONNECTION'] ?? 'mysql', ['mysql', 'mariadb'], true)) {
    exit(0);
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = (int) ($env['DB_PORT'] ?? 3306);

$waited = false;
while (! ($socket = @fsockopen($host, $port, $errno, $errstr, 5))) {
    if (! $waited) {
        echo "Waiting for MySQL at {$host}:{$port} ...\n";
        $waited = true;
    }
    sleep(10);
}
fclose($socket);

if ($waited) {
    echo "MySQL is up.\n";
}
