<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\Folder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;


class AuthController extends Controller
{
    /**
     * Failed attempts allowed for one email+IP combination before a
     * temporary lockout kicks in. Keyed by email+IP (not IP alone) so one
     * shared campus/lab network can't lock out every student at once just
     * because someone mistyped their own password a few times.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Lockout window in seconds, counted from the first failed attempt in
     * the current run - matches Laravel's own login-throttling pattern
     * (used by Fortify/Breeze), reimplemented here directly since this app
     * doesn't pull in that package.
     */
    private const LOCKOUT_DECAY_SECONDS = 60;

    public function showLogin()
    {
        return view('login');
    }

    /**
     * Requests to send a reset link allowed for one email+IP combination
     * before further requests are silently dropped (the response stays
     * identical either way - see sendResetLink()).
     */
    private const MAX_RESET_REQUESTS = 3;
    private const RESET_REQUEST_DECAY_SECONDS = 300;

    /**
     * Attempts to submit a new password allowed per IP before that IP is
     * locked out - separate from MAX_RESET_REQUESTS since this guards
     * against token-guessing, not mailbox spam.
     */
    private const MAX_RESET_SUBMISSIONS = 10;
    private const RESET_SUBMISSION_DECAY_SECONDS = 60;

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email'))) . '|' . $request->ip();
    }

    /**
     * Shared password strength policy for every place a user sets their own
     * password (first-login change and email reset). Deliberately skips
     * ->uncompromised() (Have I Been Pwned lookup) - it requires outbound
     * internet access on every password change, which isn't a fit for an
     * on-prem/offline deployment.
     */
    private function passwordPolicy(): PasswordRules
    {
        return PasswordRules::min(8)->mixedCase()->numbers()->symbols();
    }

    public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    $throttleKey = $this->throttleKey($request);

    if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
        $seconds = RateLimiter::availableIn($throttleKey);

        ActivityLog::create([
            'user_name'  => $request->input('email'),
            'activity'   => 'Login blocked - too many failed attempts',
            'ip_address' => $request->ip(),
        ]);

        return back()
            ->withErrors(['email' => "Too many login attempts. Please try again in {$seconds} seconds."])
            ->with('lockout_seconds', $seconds);
    }

    if (Auth::attempt($credentials)) {
        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $user = Auth::user();

        // An officer account with an inactive term is disabled, regardless
        // of what position it held.
        $officer = DB::table('officer_terms')
            ->where('user_id', $user->id)
            ->first();

        if ($officer && $officer->status != 'active') {
            Auth::logout();

            // A former officer's credentials still working (they're just
            // blocked past this check) is itself worth a record - could be
            // the account holder forgetting their term ended, or someone
            // else using credentials that should've stopped working.
            ActivityLog::create([
                'user_name'  => $user->name,
                'activity'   => 'Login attempt on disabled (former officer) account',
                'ip_address' => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => 'Account disabled.'
            ]);
        }

        ActivityLog::create([
            'user_name' => $user->name,
            'activity' => 'Logged in',
            'ip_address' => $request->ip()
        ]);

        // Adviser/President (or a legacy account with no officer record at
        // all) land on the SuperAdmin dashboard; every other officer gets
        // the regular one.
        return redirect(Folder::isSuperAdmin($user) ? '/superAdminDashboard' : '/adminDashboard');
    }

    RateLimiter::hit($throttleKey, self::LOCKOUT_DECAY_SECONDS);

    // Every failed attempt gets its own record now, not just the eventual
    // lockout - without this there's almost nothing to detect patterns
    // from (a brute-force run that never quite hits the lockout threshold,
    // or one spread across several accounts, would otherwise leave no
    // trace at all).
    ActivityLog::create([
        'user_name'  => $request->input('email'),
        'activity'   => 'Failed login attempt',
        'ip_address' => $request->ip(),
    ]);

    return back()->withErrors([
        'email' => 'Invalid email or password.'
    ]);
}

    public function showChangePassword()
    {
        return view('changePassword');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', $this->passwordPolicy()],
        ]);

        $user = Auth::user();
        $user->password              = Hash::make($request->password);
        $user->must_change_password  = false;
        $user->save();

        ActivityLog::create([
            'user_name'  => $user->name,
            'activity'   => 'Changed password (first-login requirement)',
            'ip_address' => $request->ip()
        ]);

        return redirect(Folder::isSuperAdmin($user) ? '/superAdminDashboard' : '/adminDashboard')
            ->with('success', 'Password changed successfully.');
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
        // The idle-timeout screen (partials.idle-timeout) submits this same
        // form with idle=1 when nobody responded to the countdown warning -
        // flagged here so the audit log and the login page both say why the
        // session ended, rather than looking like a manual logout.
        $isIdle = $request->boolean('idle');

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => $isIdle ? 'Logged out (inactivity)' : 'Logged out'
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($isIdle) {
            session()->flash('success', 'You were logged out after a period of inactivity.');
        }

        return redirect('/login');
    }

    /**
     * Pinged by the idle-timeout warning's "Stay logged in" button. Doing
     * nothing but responding 200 is the point - any authenticated request
     * already refreshes the session cookie's expiry (see config/session.php),
     * so this just gives the front end something to call that (a) resets its
     * own countdown and (b) confirms the session is still actually valid
     * (the `auth` middleware itself returns 401 for an expectsJson() request
     * if it isn't, which the widget treats as "already logged out").
     */
    public function keepAlive()
    {
        return response()->json(['ok' => true]);
    }

    public function showForgotPassword()
    {
        return view('forgotPassword');
    }

    /**
     * Every response from this endpoint is identical - success, unknown
     * email, or rate-limited - so it can't be used to enumerate which
     * addresses have accounts. Laravel's own Password::sendResetLink() adds
     * a further built-in per-email 60s throttle (config/auth.php) on top of
     * the IP+email limit enforced here.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $genericResponse = back()->with(
            'status',
            'If an account exists for that email, a password reset link is on its way.'
        );

        $throttleKey = 'reset-request|' . $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_RESET_REQUESTS)) {
            return $genericResponse;
        }

        RateLimiter::hit($throttleKey, self::RESET_REQUEST_DECAY_SECONDS);

        Password::sendResetLink($request->only('email'));

        ActivityLog::create([
            'user_name'  => $request->input('email'),
            'activity'   => 'Requested password reset link',
            'ip_address' => $request->ip(),
        ]);

        return $genericResponse;
    }

    public function showResetPassword(string $token, Request $request)
    {
        return view('resetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $throttleKey = 'reset-submit|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_RESET_SUBMISSIONS)) {
            return back()->withErrors(['email' => 'Too many attempts. Please try again in a few minutes.']);
        }

        RateLimiter::hit($throttleKey, self::RESET_SUBMISSION_DECAY_SECONDS);

        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', $this->passwordPolicy()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                $user->forceFill([
                    'password'             => Hash::make($password),
                    'must_change_password' => false,
                ])->setRememberToken(Str::random(60));

                $user->save();

                // A forgotten password can mean the old one was compromised -
                // reset every other active session for this account instead
                // of trusting them to still belong to the real owner.
                DB::table('sessions')->where('user_id', $user->id)->delete();

                ActivityLog::create([
                    'user_name'  => $user->name,
                    'activity'   => 'Reset password via emailed link',
                    'ip_address' => $request->ip(),
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Your password has been reset. Please log in.');
        }

        // Same wording whether the token was invalid, expired, or the email
        // doesn't exist - avoids leaking which case it was.
        return back()->withErrors(['email' => 'This password reset link is invalid or has expired.']);
    }
}