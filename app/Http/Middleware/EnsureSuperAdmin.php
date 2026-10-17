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
 * Guards every page that is only in the SuperAdmin menu: the SuperAdmin
 * dashboard, activity logs, backups and restore, and officer-account
 * management (anyone who reaches that can hand themselves SuperAdmin by
 * creating a President record). Before this middleware those routes sat
 * behind `auth` only - any logged-in officer could open them by typing the
 * address, even though the menu link was only ever shown to SuperAdmins.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user || ! Folder::isSuperAdmin($user)) {
            abort(403, 'Only a SuperAdmin can open this page.');
        }

        return $next($request);
    }
}
