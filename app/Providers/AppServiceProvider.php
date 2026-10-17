<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A reverse proxy or HTTPS tunnel in front of the app (config/app.php,
        // TRUSTED_PROXIES): believe its X-Forwarded-* headers, so generated
        // URLs are https on the address the visitor actually used.
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // In production every generated URL (forms, redirects, assets) is
        // https, so a login form or session cookie is never sent over plain
        // HTTP. Not applied locally, where XAMPP serves http://localhost.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Debug mode renders stack traces, config and .env values on
            // any error page - never acceptable in production.
            if (config('app.debug')) {
                Log::warning('APP_DEBUG is true in production - set APP_DEBUG=false in .env.');
            }
        }
    }
}
