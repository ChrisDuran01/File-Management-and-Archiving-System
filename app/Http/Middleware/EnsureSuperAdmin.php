<?php

namespace App\Http\Middleware;

use App\Models\Folder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts a route to users who pass Folder::isSuperAdmin() - an active
 * Adviser/President officer term, or a legacy account with no officer_terms
 * row at all.
 *
 * Officer-account management (add/edit/archive/delete/end-term) is the most
 * sensitive surface in the app: anyone who can reach it can hand themselves
 * SuperAdmin by creating a President record, or archive/delete every other
 * officer. Before this middleware, those routes sat behind `auth` only - any
 * logged-in officer could hit them directly even though the menu link was
 * only ever shown to SuperAdmins in the UI.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user || ! Folder::isSuperAdmin($user)) {
            abort(403, 'Only a SuperAdmin can manage officer accounts.');
        }

        return $next($request);
    }
}
