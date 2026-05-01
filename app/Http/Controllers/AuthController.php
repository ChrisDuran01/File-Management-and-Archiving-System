<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;

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

        // Log login activity
        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Logged in'
        ]);

        // Check the user's position instead of role
        if (Auth::user()->position_id == '1') {
            return redirect('/superAdminDashboard');
        } else {
            return redirect('/adminDashboard');
        }
    }

    return back()->withErrors([
        'email' => 'Invalid email or password.',
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