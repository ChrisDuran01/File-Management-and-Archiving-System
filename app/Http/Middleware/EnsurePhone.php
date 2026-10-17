<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Keeps the officer phone app (/app) to phones - see
 * config('officer_app.phones_only') for why. A browser only says what kind
 * of device it is through its User-Agent, which can be changed, so this is
 * "offered on phones only", not a lock: it stops the app (and the offline
 * copies it keeps) from ending up on a shared computer by accident. Anyone
 * who fakes a phone gains nothing the website's Download button doesn't
 * already give them.
 */
class EnsurePhone
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('officer_app.phones_only') || self::isPhone($request)) {
            return $next($request);
        }

        // Background calls can't show a page - refuse them plainly.
        if (! $request->isMethod('GET') || $request->expectsJson()) {
            abort(403, 'The officer app is available on phones only.');
        }

        return response()->view('OfficerApp.desktop');
    }

    /**
     * "Mobi" is what phone browsers put in their User-Agent (Android phones,
     * iPhone); tablets and computers don't, except older iPads - excluded
     * by name.
     */
    public static function isPhone(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return preg_match('/Mobi|iPhone|iPod/i', $agent) === 1
            && preg_match('/iPad|Tablet/i', $agent) !== 1;
    }
}
