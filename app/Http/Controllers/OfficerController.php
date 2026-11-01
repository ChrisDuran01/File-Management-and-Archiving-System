<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OfficerController extends Controller
{
    /**
     * Runs a mutation that could reduce who holds SuperAdmin access
     * (ending or re-positioning an officer term) and refuses to let it
     * through if it would leave literally no one able to log in as
     * SuperAdmin - which would mean no one left who could even add the next
     * batch of officers. Checked by re-counting User::superAdmins() AFTER
     * the change (inside a transaction, rolled back if the count is zero)
     * rather than predicting in advance, since the count also includes
     * legacy accounts with no officer_terms row at all.
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
        'positions'       => Position::all(),
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
     *
     * Also stores away that same school year's folders/files (formerly a
     * separate "Start New School Year" action on the Folders page). Same
     * scoping logic applies: only folders/files stamped with this
     * school_year at creation are swept, via each one's officer_term_id -
     * so anything the incoming batch already created stays untouched on
     * the active Folders page.
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

        Folder::where('is_archived', false)
            ->where('school_year', $request->school_year)
            ->update(['is_archived' => true]);

        File::where('is_archived', false)
            ->where('school_year', $request->school_year)
            ->update(['is_archived' => true]);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Ended {$request->school_year} officer term (moved to former, folders/files stored)",
            'ip_address' => request()->ip()
        ]);

        return back()->with('success', "Officers for {$request->school_year} moved to former, and their folders/files have been stored under that school year.");
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

    /**
     * Reactivate a former officer by starting a brand new term for them -
     * asks for the school year (and lets the position be changed, in case
     * they won a different seat) instead of silently reusing the year their
     * old, ended term was under. Without this, a returning officer would
     * come back mislabeled with a stale school_year: they'd show up as a
     * phantom "current" year in the End Term picker, and any folders/files
     * they create would never get archived when the real current year ends.
     */
    public function reactivate(Request $request, $id)
    {
        $request->validate([
            'school_year' => 'required|string|max:255',
            'position_id' => 'required|exists:positions,id',
        ]);

        $term = OfficerTerm::with('user')->findOrFail($id);

        $term->update([
            'status'      => 'active',
            'school_year' => $request->school_year,
            'position_id' => $request->position_id,
            'term_start'  => now(),
            'term_end'    => null,
        ]);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Reactivated officer: {$term->user->name} for SY {$request->school_year}",
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Officer reactivated for the new term.');
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

        return redirect()->route('officers.index')->with('success', 'Officer updated.');
    }

    /**
     * A temporary password for an officer who can't use "Forgot password"
     * (no access to their email, or a wrong one on the account). Like a new
     * account's first password, it must be changed at the next login, and
     * every device still logged in as them is logged out, so the person
     * who set it can't stay in the account. Your own password is changed
     * from Profile instead.
     */
    public function resetPassword(Request $request, $id)
    {
        $term = OfficerTerm::with('user')->findOrFail($id);
        $user = $term->user;

        if ($user->is(Auth::user())) {
            return back()->with('error', 'Change your own password from Profile.');
        }

        if (! $user->isActiveOfficer()) {
            return back()->with('error', 'Former officers can\'t log in. Reactivate them first.');
        }

        $request->validate([
            'password' => ['required', AuthController::passwordPolicy()],
        ]);

        $user->forceFill([
            'password'             => Hash::make($request->password),
            'must_change_password' => true,
        ])->setRememberToken(Str::random(60));
        $user->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Reset password for officer: ' . $user->name,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('officers.edit', $term->id)
            ->with('success', "Password reset. {$user->name} must choose a new one at their next login.");
    }
}