<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs out an officer whose term has ended (User::isActiveOfficer()).
 *
 * The sign-in page already turns a former officer away (AuthController::
 * login), but someone who was signed in when End Term ran - on the website
 * or in the phone app - would otherwise keep going until their session ran
 * out. This runs on every signed-in page, so their next click is the last.
 * Reactivate gives the term back, and they can sign in again.
 */
class EnsureActiveOfficer
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user || $user->isActiveOfficer()) {
            return $next($request);
        }

        ActivityLog::create([
            'user_name'  => $user->name,
            'activity'   => 'Logged out (officer term ended)',
            'ip_address' => $request->ip(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Account disabled.'], 401);
        }

        return redirect()->route($request->is('app', 'app/*') ? 'app.login' : 'login')
            ->withErrors(['email' => 'Account disabled.']);
    }
}
