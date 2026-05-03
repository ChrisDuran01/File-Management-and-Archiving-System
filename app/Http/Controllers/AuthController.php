<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;


class AuthController extends Controller
{
    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    if (Auth::attempt($credentials)) {

        $request->session()->regenerate();

        $user = Auth::user();

        // Check if user is an officer
        $officer = DB::table('officer_terms')
            ->where('user_id', $user->id)
            ->first();

        // If officer exists, check status
        if ($officer) {

            if ($officer->status != 'active') {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Account disabled.'
                ]);
            }

            ActivityLog::create([
                'user_name' => $user->name,
                'activity' => 'Logged in',
                'ip_address' => $request->ip()
            ]);

            return redirect('/adminDashboard');
        }

        // Super Admin / Normal User
        ActivityLog::create([
            'user_name' => $user->name,
            'activity' => 'Logged in',
            'ip_address' => $request->ip()
        ]);

        return redirect('/superAdminDashboard');
    }

    return back()->withErrors([
        'email' => 'Invalid email or password.'
    ]);
}

    public function adminDashboard()
    {
        return view('Admin.adminDashboard');
    }

    public function superAdminDashboard()
    {
        return view('SuperAdmin.superAdminDashboard');
    }

    public function logout(Request $request)
    {
        // Log logout activity
        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Logged out'
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }


}