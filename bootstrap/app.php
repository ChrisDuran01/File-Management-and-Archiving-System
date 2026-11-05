<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
            \App\Http\Middleware\EnsurePrivacyNoticeAcknowledged::class,
            \App\Http\Middleware\EnsureTwoFactorEnabled::class,
        ]);

        // Anonymous student-page analytics beacon - see the route's comment.
        $middleware->validateCsrfTokens(except: ['engagement']);

        // Only APP_URL's host (and its subdomains) is answered. Links in
        // emails - the password reset link above all - are built from the
        // address the request came in on, so without this a request sent
        // with someone else's Host or X-Forwarded-Host would mail out a reset
        // link to their site. Laravel skips it when APP_ENV=local and in
        // tests, so it bites on the server only: APP_URL must be right there.
        $middleware->trustHosts();

        // X-Frame-Options, nosniff, Referrer-Policy, HSTS on HTTPS.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'nocache'    => \App\Http\Middleware\PreventBackHistory::class,
            'phone'      => \App\Http\Middleware\EnsurePhone::class,
            'officer'    => \App\Http\Middleware\EnsureActiveOfficer::class,
        ]);

        // Someone not signed in goes to the sign-in page at the address of
        // whichever side they were on: the same page, but inside the officer
        // phone app it stays at /app/login, so an installed app never drops
        // out to a browser bar.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('app', 'app/*')
            ? route('app.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
