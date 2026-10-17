<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Holds a signed-in user on the privacy notice until they have acknowledged
 * the current version (config('privacy.version')). Bumping the version makes
 * everyone acknowledge again. Runs after EnsurePasswordIsChanged, and lets
 * the password-change screen through so the two never redirect-loop.
 */
class EnsurePrivacyNoticeAcknowledged
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user
            || $user->privacy_notice_version === config('privacy.version')
            || $request->routeIs(
                'privacy.notice', 'privacy.acknowledge', 'privacy.acknowledge.submit',
                'password.change', 'password.change.submit', 'logout', 'session.keepAlive'
            )) {
            return $next($request);
        }

        // Background/AJAX calls (bell polling, help bot) can't follow a
        // redirect to an HTML page - refuse them until the notice is read.
        if (! $request->isMethod('GET') || $request->expectsJson()) {
            abort(403, 'Please read and acknowledge the privacy notice first.');
        }

        return redirect()->route('privacy.acknowledge');
    }
}
