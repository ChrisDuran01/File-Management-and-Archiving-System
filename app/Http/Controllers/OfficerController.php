<?php

namespace App\Http\Controllers;

use App\Models\OfficerTerm;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;

class OfficerController extends Controller
{
    // DISPLAY OFFICERS
    public function index()
{
    $currentOfficers = OfficerTerm::with(['user', 'position'])
        ->where('status', 'active')
        ->get();

    $formerOfficers = OfficerTerm::with(['user', 'position'])
        ->where('status', 'former')
        ->get();

    return view('SuperAdmin.manageAdmins', [
        'currentOfficers' => $currentOfficers,
        'formerOfficers'  => $formerOfficers,
    ]);
}

    // CREATE OFFICER (USER + TERM)
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required',
            'email'       => 'required|email|unique:users',
            'password'    => 'required|min:6',
            'position_id' => 'required|exists:positions,id',
            'school_year' => 'required',
        ]);

        // 1. Create user (identity)
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // 2. Prevent duplicate active term per year
        $exists = OfficerTerm::where('user_id', $user->id)
            ->where('school_year', $request->school_year)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            return back()->with('error', 'This officer already exists for this school year.');
        }

        // 3. Create officer term
        OfficerTerm::create([
            'user_id'     => $user->id,
            'position_id' => $request->position_id,
            'school_year' => $request->school_year,
            'term_start'  => now(),
            'status'      => 'active',
        ]);

        return redirect()->route('officers.index')
            ->with('success', 'Officer created successfully.');
    }

    // END CURRENT TERM (ALL ACTIVE → FORMER)
    public function archiveAll()
    {
        OfficerTerm::where('status', 'active')
            ->update([
                'status'   => 'former',
                'term_end' => now(),
            ]);
        
        return back()->with('success', 'All current officers moved to former.');
    }

    // REACTIVATE OFFICER
    public function reactivate($id)
    {
        $term = OfficerTerm::findOrFail($id);

        $term->update([
            'status'     => 'active',
            'term_start' => now(),
            'term_end'   => null,
        ]);

        return back()->with('success', 'Officer reactivated.');
    }

    // DELETE OFFICER TERM RECORD
    public function destroy($id)
    {
        OfficerTerm::findOrFail($id)->delete();

        return back()->with('success', 'Officer record deleted.');
    }

    // CREATE FORM
    public function create()
    {
        $users = User::all();
        $positions = Position::all();

        return view('SuperAdmin.officers.create', compact('users', 'positions'));
    }

    // EDIT FORM
    public function edit($id)
    {
        $term = OfficerTerm::findOrFail($id);
        $users = User::all();
        $positions = Position::all();

        return view('SuperAdmin.officers.edit', compact('term', 'users', 'positions'));
    }

    // UPDATE TERM
    public function update(Request $request, $id)
    {
        $request->validate([
            'user_id'     => 'required|exists:users,id',
            'position_id' => 'required|exists:positions,id',
            'school_year' => 'required',
        ]);

        $term = OfficerTerm::findOrFail($id);

        $term->update([
            'user_id'     => $request->user_id,
            'position_id' => $request->position_id,
            'school_year' => $request->school_year,
        ]);

        return back()->with('success', 'Officer updated successfully.');
    }
}