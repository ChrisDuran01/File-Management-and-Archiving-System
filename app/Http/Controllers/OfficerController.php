<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\Position;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\SchoolYear;
use App\Support\StudentPageCache;
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
        'handover'        => $this->handover(),
    ]);
}

    /**
     * What the End Term box shows: the school year being handed over, the
     * next President (or who can be picked as one), who leaves, the Adviser
     * who stays, and whether the person looking will be signed out.
     */
    private function handover(): object
    {
        $year     = OfficerTerm::currentSchoolYear();
        $nextYear = SchoolYear::isValid($year) ? SchoolYear::next($year) : null;
        $incoming = OfficerTerm::incomingPresident();

        $leaving = OfficerTerm::with(['user', 'position'])
            ->where('status', 'active')
            ->where('school_year', $year)
            ->get();
        [$advisers, $leaving] = $leaving->partition(fn ($t) => $t->position?->position_name === Position::ADVISER);

        $myTerms = OfficerTerm::with('position')->where('user_id', Auth::id())->where('status', 'active')->get();

        // Anyone who has been an officer can be the next President (re-elected,
        // or an officer who ran for it) - not the Adviser, and not someone who
        // already holds a term in the next school year.
        $candidates = $incoming || ! $nextYear ? collect() : User::whereHas('officerTerms')
            ->whereDoesntHave('officerTerms', fn ($q) => $q->where('status', 'active')->where(fn ($q) => $q
                ->where('school_year', $nextYear)
                ->orWhereHas('position', fn ($p) => $p->where('position_name', Position::ADVISER))))
            ->with(['officerTerms' => fn ($q) => $q->with('position')->orderByDesc('school_year')->orderByDesc('id')])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $latest = $user->officerTerms->first();

                return (object) [
                    'id'    => $user->id,
                    'label' => $user->name . ' - ' . ($latest->position->position_name ?? 'Officer') . ' ' . $latest->school_year
                        . ($latest->status === 'former' ? ' (former)' : ''),
                ];
            });

        return (object) [
            'year'        => $year,
            'nextYear'    => $nextYear,
            'incoming'    => $incoming,
            'ready'       => $incoming && self::isReadyToTakeOver($incoming->user),
            'leaving'     => $leaving,
            'advisers'    => $advisers,
            'candidates'  => $candidates,
            'signsYouOut' => $myTerms->isNotEmpty() && $myTerms->every(
                fn ($t) => $t->school_year === $year && $t->position?->position_name !== Position::ADVISER
            ),
        ];
    }

    /**
     * Officers join the school year in office. The next one starts only
     * through End Term, so two years' officers are never in office together;
     * while the next President waits for End Term, nobody is added.
     */
    public static function handoverPendingMessage(OfficerTerm $incoming): string
    {
        return "End the current term first. After {$incoming->user->name} takes over as President, "
            . "the {$incoming->school_year} officers are added.";
    }

    /** A school year typed in by hand - only when no officers are in office yet. */
    private static function schoolYearRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (! SchoolYear::isValid($value)) {
                $fail('Write the school year like 2026-2027.');
            }
        };
    }

    /**
     * A new account and its officer term, saved together or not at all. An
     * account left without a term counts as SuperAdmin (Folder::isSuperAdmin),
     * so a failure between the two must never leave just the account.
     */
    private function createOfficerAccount(Request $request, int|string $positionId, string $schoolYear): User
    {
        return DB::transaction(function () use ($request, $positionId, $schoolYear) {
            $user = User::create([
                'name'                 => $request->name,
                'email'                => $request->email,
                'password'             => bcrypt($request->password),
                'must_change_password' => true,
            ]);

            OfficerTerm::create([
                'user_id'     => $user->id,
                'position_id' => $positionId,
                'school_year' => $schoolYear,
                'term_start'  => now(),
                'status'      => 'active',
            ]);

            return $user;
        });
    }

    // CREATE OFFICER (USER + TERM)
    public function store(Request $request)
    {
        if ($incoming = OfficerTerm::incomingPresident()) {
            return redirect()->route('officers.index')->with('error', self::handoverPendingMessage($incoming));
        }

        $currentYear = OfficerTerm::currentSchoolYear();

        $request->validate([
            'name'        => 'required',
            'email'       => 'required|email|unique:users',
            'password'    => ['required', AuthController::passwordPolicy()],
            'position_id' => 'required|exists:positions,id',
            'school_year' => $currentYear ? 'nullable' : ['required', self::schoolYearRule()],
        ]);

        $schoolYear = $currentYear ?? $request->school_year;

        $user = $this->createOfficerAccount($request, $request->position_id, $schoolYear);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Added officer: ' . $user->name,
            'ip_address' => $request->ip()
        ]);

        return redirect()->route('officers.index')
            ->with('success', 'Officer created successfully.');
    }

    /**
     * The next President for End Term: a new account (with a temporary
     * password they must change), or someone already in the system - a
     * re-elected President, or an officer who ran for President. Their term
     * is in the next school year, so End Term leaves it alone. End Term
     * stays locked until they have signed in and chosen their own password,
     * which proves the account works before the outgoing officers leave.
     */
    public function storeNextPresident(Request $request)
    {
        $year = OfficerTerm::currentSchoolYear();

        if (! SchoolYear::isValid($year)) {
            return back()->with('error', 'There are no officers in office to hand over from.');
        }

        if (OfficerTerm::incomingPresident()) {
            return back()->with('error', 'The next President has already been added.');
        }

        $president = Position::where('position_name', Position::PRESIDENT)->first();
        if (! $president) {
            return back()->with('error', 'There is no President position to give the next President.');
        }

        $nextYear = SchoolYear::next($year);
        $isNew    = $request->input('mode') !== 'existing';

        $request->validateWithBag('handover', $isNew ? [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', AuthController::passwordPolicy()],
        ] : [
            'user_id'  => 'required|integer|exists:users,id',
        ], [
            'user_id.required' => 'Pick who the next President is.',
        ]);

        if ($isNew) {
            $user = $this->createOfficerAccount($request, $president->id, $nextYear);
        } else {
            $user = User::findOrFail($request->user_id);

            if (! $user->officerTerms()->exists()) {
                return back()->with('error', "{$user->name} has never been an officer.");
            }

            if ($user->officerTerms()->where('status', 'active')->where('school_year', $nextYear)->exists()) {
                return back()->with('error', "{$user->name} already has a {$nextYear} term. Change its position with Edit instead.");
            }

            OfficerTerm::create([
                'user_id'     => $user->id,
                'position_id' => $president->id,
                'school_year' => $nextYear,
                'term_start'  => now(),
                'status'      => 'active',
            ]);
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Added officer: ' . $user->name . " (next President, {$nextYear})",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', $isNew
                ? "{$user->name} added. They sign in with the temporary password and choose their own; then the term can end."
                : "{$user->name} added as the next President.")
            ->with('openHandover', true);
    }

    /**
     * Takes back the next President before End Term - a wrong person picked,
     * or an account made for the wrong email. An account made just for this
     * that was never signed in to is deleted outright. One that was signed
     * in to is kept as a former officer instead, so whatever they did stays
     * traceable; someone who had other terms keeps those.
     */
    public function removeNextPresident(Request $request)
    {
        $term = OfficerTerm::incomingPresident();

        if (! $term) {
            return back()->with('error', 'There is no next President to remove.');
        }

        $user        = $term->user;
        $hasOther    = $user->officerTerms()->whereKeyNot($term->id)->exists();
        $neverSignedIn = $user->must_change_password;

        try {
            $this->guardLastSuperAdmin(function () use ($term, $user, $hasOther, $neverSignedIn) {
                if ($hasOther) {
                    $term->delete();
                } elseif ($neverSignedIn) {
                    $user->notifications()->delete();
                    $user->forceDelete(); // takes the term with it
                } else {
                    $term->update(['status' => 'former', 'term_end' => now()]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Removed next President: ' . $user->name,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', ! $hasOther && ! $neverSignedIn
                ? "{$user->name} is no longer the next President. They had already signed in, so the account is kept under Former Officers."
                : "{$user->name} is no longer the next President.")
            ->with('openHandover', true);
    }

    /**
     * End Term: the current school year's officers move to Former Officers
     * together, handing over to the next President added above. Only that
     * one school year is ended - the next President's term is in the year
     * after and stays. The Adviser isn't elected each year, so instead of
     * leaving, their term carries over into the next school year (the old
     * one is kept as their record for this year).
     *
     * Also stores away that same school year's folders/files (formerly a
     * separate "Start New School Year" action on the Folders page). Only
     * folders/files stamped with this school_year at creation are swept -
     * anything the next President already made stays on the Folders page.
     *
     * Whoever ran it and has no term left is signed out right away.
     */
    public function archiveAll(Request $request)
    {
        $request->validate([
            'school_year' => 'required|string',
        ]);

        $year = OfficerTerm::currentSchoolYear();

        if ($request->school_year !== $year) {
            return back()->with('error', 'The officers changed since this page was opened. Open End Term again.');
        }

        $incoming = OfficerTerm::incomingPresident();

        if (! $incoming) {
            return back()->with('error', 'Add the next President before ending the term.')->with('openHandover', true);
        }

        if (! self::isReadyToTakeOver($incoming->user)) {
            return back()->with('error', "{$incoming->user->name} has to sign in, choose their own password and set up two-factor sign-in before the term can end.")
                ->with('openHandover', true);
        }

        $nextYear = $incoming->school_year;
        $leavingUserIds = OfficerTerm::where('status', 'active')->where('school_year', '<=', $year)->pluck('user_id')->unique();

        try {
            $this->guardLastSuperAdmin(function () use ($year, $nextYear) {
                $advisers = OfficerTerm::where('status', 'active')
                    ->where('school_year', '<=', $year)
                    ->whereHas('position', fn ($p) => $p->where('position_name', Position::ADVISER))
                    ->get();

                foreach ($advisers as $term) {
                    $alreadyThere = OfficerTerm::where('user_id', $term->user_id)
                        ->where('status', 'active')
                        ->where('school_year', $nextYear)
                        ->exists();

                    if (! $alreadyThere) {
                        OfficerTerm::create([
                            'user_id'     => $term->user_id,
                            'position_id' => $term->position_id,
                            'school_year' => $nextYear,
                            'term_start'  => now(),
                            'status'      => 'active',
                            'site_photo'  => $term->site_photo,
                        ]);
                    }

                    $term->update(['status' => 'former', 'term_end' => now()]);
                }

                OfficerTerm::where('status', 'active')
                    ->where('school_year', $year)
                    ->update([
                        'status'   => 'former',
                        'term_end' => now(),
                    ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Folder::where('is_archived', false)
            ->where('school_year', $year)
            ->update(['is_archived' => true]);

        File::where('is_archived', false)
            ->where('school_year', $year)
            ->update(['is_archived' => true]);

        StudentPageCache::forget(); // the bulk updates above skip model events
        self::forgetDevicesOfFormerOfficers($leavingUserIds);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Ended {$year} officer term (moved to former, folders/files stored), next President: {$incoming->user->name}",
            'ip_address' => $request->ip()
        ]);

        if (! Auth::user()->isActiveOfficer()) {
            return $this->signOutEndedOfficer($request, "Your term has ended. {$incoming->user->name} is now the President.");
        }

        return back()->with('success', "The {$year} officers moved to Former Officers and their folders/files were stored. "
            . "{$incoming->user->name} is now the President and adds the {$nextYear} officers.");
    }

    /**
     * The next President can take over once the account is proven to work:
     * signed in, own password chosen, and two-factor set up (when required),
     * so the outgoing officers never leave behind an account nobody can use.
     */
    private static function isReadyToTakeOver(User $user): bool
    {
        return ! $user->must_change_password && ($user->hasTwoFactor() || ! config('two_factor.required'));
    }

    /**
     * A former officer can't sign in, but their "Trust this device" stays
     * forgotten too, so a reactivated officer is asked for a code again.
     */
    private static function forgetDevicesOfFormerOfficers($userIds): void
    {
        User::whereIn('id', $userIds)->get()
            ->reject(fn (User $user) => $user->isActiveOfficer())
            ->each(fn (User $user) => $user->forgetTrustedDevices());
    }

    /**
     * Signs out the officer who just ended their own term straight away,
     * with a plain message, instead of the "Account disabled." that
     * EnsureActiveOfficer would show them on their next click.
     */
    private function signOutEndedOfficer(Request $request, string $message)
    {
        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Logged out (officer term ended)',
            'ip_address' => $request->ip(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', $message);
    }

    public function archiveOfficer(Request $request, $id)
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

        self::forgetDevicesOfFormerOfficers([$term->user_id]);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Archived officer: ' . $term->user->name,
            'ip_address' => $request->ip()
        ]);

        if ($term->user->is(Auth::user()) && ! Auth::user()->isActiveOfficer()) {
            return $this->signOutEndedOfficer($request, 'Your term has ended.');
        }

        return back()->with('success', 'Officer term archived.');
    }

    /**
     * Reactivate a former officer by starting a brand new term for them -
     * asks for the school year (and lets the position be changed, in case
     * they won a different seat) instead of silently reusing the year their
     * old, ended term was under. Without this, a returning officer would
     * come back mislabeled with a stale school_year: they'd show up as a
     * phantom "current" year in End Term, and any folders/files they create
     * would never get archived when the real current year ends. Like Add
     * Officer, it is always the school year in office.
     */
    public function reactivate(Request $request, $id)
    {
        if ($incoming = OfficerTerm::incomingPresident()) {
            return back()->with('error', self::handoverPendingMessage($incoming));
        }

        $currentYear = OfficerTerm::currentSchoolYear();

        $request->validate([
            'school_year' => $currentYear ? 'nullable' : ['required', 'string', 'max:255', self::schoolYearRule()],
            'position_id' => 'required|exists:positions,id',
        ]);

        $term = OfficerTerm::with('user')->findOrFail($id);
        $schoolYear = $currentYear ?? $request->school_year;

        $term->update([
            'status'      => 'active',
            'school_year' => $schoolYear,
            'position_id' => $request->position_id,
            'term_start'  => now(),
            'term_end'    => null,
        ]);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Reactivated officer: {$term->user->name} for SY {$schoolYear}",
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
        $user->forgetTrustedDevices();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Reset password for officer: ' . $user->name,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('officers.edit', $term->id)
            ->with('success', "Password reset. {$user->name} must choose a new one at their next login.");
    }

    /**
     * For an officer who lost their phone and their recovery codes: their
     * two-factor is taken off, every device signed in as them is signed out,
     * and they set it up again with their app at the next sign-in. Your own
     * goes through Profile ("Set up on a new phone"); if both the Adviser and
     * the President are locked out, `php artisan two-factor:reset` on the
     * server is the last resort.
     */
    public function resetTwoFactor(Request $request, $id, TwoFactor $twoFactor)
    {
        $term = OfficerTerm::with('user')->findOrFail($id);
        $user = $term->user;

        if ($user->is(Auth::user())) {
            return back()->with('error', 'For your own account, use "Set up on a new phone" in Profile.');
        }

        if (! $user->isActiveOfficer()) {
            return back()->with('error', 'Former officers can\'t log in. Reactivate them first.');
        }

        $twoFactor->reset($user);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Reset two-factor for officer: ' . $user->name,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('officers.edit', $term->id)
            ->with('success', "Two-factor reset. {$user->name} sets it up again with their app at the next sign-in.");
    }
}