<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Folder;
use App\Models\ActivityLog;
use App\Models\Position;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display Super Admin dashboard summary
     * Shows total users, folders, logs, and latest activities
     */
    public function superAdminDashboard()
    {
        // Count total records
        $users = User::count();
        $folders = Folder::count();
      //  $logs = ActivityLog::count();

        // Get latest 10 activity logs
        $activities = ActivityLog::latest()->take(10)->get();

        // Pass data to dashboard view
        return view('SuperAdmin.superAdminDashboard', compact(
            'users',
            'folders',
         //   'logs',
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