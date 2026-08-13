<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Folder;
use App\Models\File;
use App\Models\Backup;
use App\Models\OfficerTerm;
use App\Models\ActivityLog;
use App\Models\Position;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display Super Admin dashboard summary
     * Shows system-wide totals, officer standing, backup status, and recent activity
     */
    public function superAdminDashboard()
    {
        $users   = User::count();
        $folders = Folder::count();
        $files   = File::count();

        $storageUsed = File::sum('size');
        $storageFormatted = $storageUsed >= 1073741824
            ? number_format($storageUsed / 1073741824, 2) . ' GB'
            : number_format($storageUsed / 1048576, 2) . ' MB';

        $activeOfficers = OfficerTerm::where('status', 'active')->count();
        $formerOfficers = OfficerTerm::where('status', 'former')->count();

        $latestBackup = Backup::latest()->first();

        // Get latest 10 activity logs
        $activities = ActivityLog::latest()->take(10)->get();

        // Pass data to dashboard view
        return view('SuperAdmin.superAdminDashboard', compact(
            'users',
            'folders',
            'files',
            'storageFormatted',
            'activeOfficers',
            'formerOfficers',
            'latestBackup',
            'activities'
        ));
    }

    /**
     * Show all officers (users with assigned positions)
     */
    public function officers()
    {
        $users = User::with('position') // eager load position relationship
                    ->whereNotNull('position_id') // only users with positions
                    ->get();

        return view('SuperAdmin.manageAdmins', compact('users'));
    }

    /**
     * Show form to create a new officer
     */
    public function createOfficer()
    {
        // Get all positions for dropdown selection
        $positions = Position::all();

        return view('SuperAdmin.addAdmin', compact('positions'));
    }

    /**
     * Store new officer in database
     */
    public function storeOfficer(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'position_id' => 'required|exists:positions,id'
        ]);

        // Create new user with hashed password
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'position_id' => $request->position_id,
        ]);

        return redirect()->route('officers.index')
            ->with('success', 'Officer added successfully!');
    }

    /**
     * Alternative method to display officers
     * Excludes position_id = 1 (likely Super Admin)
     */
    public function officer()
    {
        $users = User::with('position')
            ->whereNotNull('position_id')
            ->where('position_id', '!=', 1) // exclude specific role
            ->get();

        return view('SuperAdmin.manageAdmins', compact('users'));
    }

    /**
     * Show edit form for a specific officer
     */
    public function edit($id)
    {
        // Find user or fail if not found
        $user = User::findOrFail($id);

        // Get all positions for dropdown
        $positions = Position::all();

        return view('SuperAdmin.editAdmin', compact('user', 'positions'));
    }

    /**
     * Update officer details
     */
    public function update(Request $request, $id)
    {
        // Find user
        $user = User::findOrFail($id);

        // Update user data
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'position_id' => $request->position_id,
        ]);

        return redirect()->route('officers.index')
            ->with('success', 'Officer updated successfully.');
    }
}