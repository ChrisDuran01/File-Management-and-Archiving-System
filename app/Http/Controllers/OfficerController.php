<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\OfficerTerm;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OfficerController extends Controller
{
    /**
     * Runs a mutation that could reduce who holds SuperAdmin access
     * (ending/deleting/re-positioning an officer term) and refuses to let it
     * through if it would leave literally no one able to log in as
     * SuperAdmin - which would mean no one left who could even add the next
     * batch of officers. Checked by re-counting User::superAdmins() AFTER
     * the change (inside a transaction, rolled back if the count is zero)
     * rather than predicting in advance, because a naive "is this the last
     * active Adviser/President term" check gets destroy() wrong: deleting a
     * user's only officer_terms row doesn't remove their admin access, it
     * actually grants it via the legacy "no terms at all" fallback rule -
     * so the only reliable check is what the count actually looks like
     * after the write.
     *
     * @throws RuntimeException with a user-facing message if blocked
     */
    private function guardLastSuperAdmin(\Closure $mutate): void
    {
        DB::transaction(function () use ($mutate) {
            $mutate();

            if (User::superAdmins()->count() === 0) {
                throw new RuntimeException(
                    'This would leave no one able to log in as SuperAdmin to manage officers. '
                    . 'Make sure at least one active Adviser or President remains before doing this.'
                );
            }
        });
    }

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
            'name'                  => $request->name,
            'email'                 => $request->email,
            'password'              => bcrypt($request->password),
            'must_change_password'  => true,
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

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Added officer: ' . $user->name,
            'ip_address' => $request->ip()
        ]);

        return redirect()->route('officers.index')
            ->with('success', 'Officer created successfully.');
    }

    /**
     * End the term for one school year's officers (all of them move to
     * Former Officers together). Scoped to a single school_year rather than
     * "every active row" - the proper handover order is to add the incoming
     * batch (a new school_year) BEFORE ending the outgoing one, which means
     * both years are briefly active at once. A blanket "end everything
     * active" would sweep up the brand-new incoming officers along with the
     * outgoing ones the moment they're created - exactly the accident this
     * scoping prevents.
     */
    public function archiveAll(Request $request)
    {
        $request->validate([
            'school_year' => 'required|string',
        ]);

        try {
            $this->guardLastSuperAdmin(function () use ($request) {
                OfficerTerm::where('status', 'active')
                    ->where('school_year', $request->school_year)
                    ->update([
                        'status'   => 'former',
                        'term_end' => now(),
                    ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Ended {$request->school_year} officer term (moved to former)",
            'ip_address' => request()->ip()
        ]);

        return back()->with('success', "Officers for {$request->school_year} moved to former.");
    }

    public function archiveOfficer($id)
    {
        $term = OfficerTerm::with('user')->findOrFail($id);

        try {
            $this->guardLastSuperAdmin(function () use ($term) {
                $term->update([
                    'status'     => 'former',
                    'term_end'   => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Archived officer: ' . $term->user->name,
            'ip_address' => request()->ip()
        ]);

        return back()->with('success', 'Officer term archived.');
    }

    // REACTIVATE OFFICER
    public function reactivate($id)
    {
        $term = OfficerTerm::with('user')->findOrFail($id);

        $term->update([
            'status'     => 'active',
            'term_start' => now(),
            'term_end'   => null,
        ]);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Reactivated officer: ' . $term->user->name,
            'ip_address' => request()->ip()
        ]);

        return back()->with('success', 'Officer reactivated.');
    }

    // DELETE OFFICER TERM RECORD
    public function destroy($id)
    {
        $term = OfficerTerm::with('user')->findOrFail($id);
        $officerName = $term->user->name;

        try {
            $this->guardLastSuperAdmin(function () use ($term) {
                $term->delete();
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Deleted officer record: ' . $officerName,
            'ip_address' => request()->ip()
        ]);

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
        $term = OfficerTerm::with('user')->findOrFail($id);
        $user = $term->user;
        $positions = Position::all();

        return view('SuperAdmin.editAdmin', compact('term', 'user', 'positions'));
    }

    // UPDATE TERM
    public function update(Request $request, $id)
    {
        $term = OfficerTerm::findOrFail($id);

        $request->validate([
            'name'        => 'required',
            'email'       => 'required|email|unique:users,email,'.$term->user_id,
            'position_id' => 'required|exists:positions,id',
        ]);

        $user = User::findOrFail($term->user_id);

        try {
            $this->guardLastSuperAdmin(function () use ($user, $term, $request) {
                $user->update([
                    'name'  => $request->name,
                    'email' => $request->email,
                ]);

                $term->update([
                    'position_id' => $request->position_id,
                ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Updated officer: ' . $user->name,
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Officer updated successfully.');
    }
}