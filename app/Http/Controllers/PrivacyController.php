<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrivacyController extends Controller
{
    /** Public read-only copy, linked from the login page. */
    public function notice()
    {
        return view('privacy', ['pending' => false]);
    }

    /** Same notice with the acknowledge form, shown to a signed-in user who hasn't accepted the current version. */
    public function acknowledge()
    {
        if (Auth::user()->privacy_notice_version === config('privacy.version')) {
            return $this->home(Auth::user());
        }

        return view('privacy', ['pending' => true]);
    }

    public function submitAcknowledge(Request $request)
    {
        $request->validate([
            'acknowledge' => 'accepted',
        ], [
            'acknowledge.accepted' => 'Please tick the box to confirm you have read the privacy notice.',
        ]);

        $user = Auth::user();
        $user->forceFill([
            'privacy_acknowledged_at' => now(),
            'privacy_notice_version'  => config('privacy.version'),
        ])->save();

        ActivityLog::create([
            'user_name'  => $user->name,
            'activity'   => 'Acknowledged privacy notice (version ' . config('privacy.version') . ')',
            'ip_address' => $request->ip(),
        ]);

        return $this->home($user);
    }

    private function home($user)
    {
        return redirect(Folder::isSuperAdmin($user) ? '/superAdminDashboard' : '/adminDashboard');
    }
}
