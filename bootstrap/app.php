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
        ]);

        // Anonymous student-page analytics beacon - see the route's comment.
        $middleware->validateCsrfTokens(except: ['engagement']);

        $middleware->alias([
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'nocache'    => \App\Http\Middleware\PreventBackHistory::class,
            'phone'      => \App\Http\Middleware\EnsurePhone::class,
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
