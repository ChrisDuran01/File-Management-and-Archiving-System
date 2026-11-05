<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Holds a signed-in account on the two-factor setup page until it has set
 * up an authenticator app (config('two_factor.required')). Runs after
 * EnsurePasswordIsChanged and EnsurePrivacyNoticeAcknowledged and lets their
 * pages through, so a new officer goes: own password → privacy notice →
 * two-factor, and none of them redirect-loop into each other. Officers
 * already signed in when this went live are held on their next click.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user
            || ! config('two_factor.required')
            || $user->hasTwoFactor()
            || $request->routeIs(
                'two-factor.setup', 'two-factor.setup.confirm', 'two-factor.recovery-codes',
                'password.change', 'password.change.submit',
                'privacy.notice', 'privacy.acknowledge', 'privacy.acknowledge.submit',
                'logout', 'session.keepAlive'
            )) {
            return $next($request);
        }

        // Background/AJAX calls (bell polling, uploads) can't follow a
        // redirect to an HTML page - refuse them until it is set up.
        if (! $request->isMethod('GET') || $request->expectsJson()) {
            abort(403, 'Set up two-factor sign-in first.');
        }

        return redirect()->route('two-factor.setup');
    }
}
