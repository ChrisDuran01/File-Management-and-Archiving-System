<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Two-factor sign-in with an authenticator app, required for every account
 * (config/two_factor.php):
 *
 * - challenge: after a right password, the 6-digit code (or a recovery
 *   code). AuthController::login() leaves the account waiting in the
 *   session; nobody is signed in until the code checks out.
 * - setup: a signed-in account without it is held here
 *   (EnsureTwoFactorEnabled) until it scans the QR code and types a code.
 * - recovery codes: shown once, after setup and when made again in Profile.
 * - Profile: new recovery codes, forget trusted devices, move to a new phone.
 *
 * The Adviser/President reset an officer's from Edit Officer
 * (OfficerController::resetTwoFactor); `php artisan two-factor:reset` is the
 * last resort on the server.
 */
class TwoFactorController extends Controller
{
    /** Wrong codes allowed per account per minute before it has to wait. */
    private const MAX_ATTEMPTS = 5;

    public function __construct(private TwoFactor $twoFactor)
    {
    }

    public function showChallenge(Request $request)
    {
        if (! $this->waitingUser($request)) {
            return $this->backToSignIn($request);
        }

        return view('twoFactor.challenge', ['fromApp' => (bool) $request->session()->get('two_factor.login.app')]);
    }

    public function challenge(Request $request)
    {
        $user = $this->waitingUser($request);

        if (! $user) {
            return $this->backToSignIn($request);
        }

        $request->validate([
            'code'          => 'nullable|string|max:20',
            'recovery_code' => 'nullable|string|max:40',
        ]);

        $key = 'two-factor|' . $user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            ActivityLog::create([
                'user_name'  => $user->name,
                'activity'   => 'Two-factor blocked - too many wrong codes',
                'ip_address' => $request->ip(),
            ]);

            return back()->withErrors(['code' => 'Too many wrong codes. Try again in ' . RateLimiter::availableIn($key) . ' seconds.']);
        }

        $usedRecovery = $request->filled('recovery_code');
        $passed = $usedRecovery
            ? $this->twoFactor->useRecoveryCode($user, $request->input('recovery_code'))
            : $this->twoFactor->verify($user, (string) $request->input('code'));

        if (! $passed) {
            RateLimiter::hit($key, 60);

            ActivityLog::create([
                'user_name'  => $user->name,
                'activity'   => 'Failed two-factor code',
                'ip_address' => $request->ip(),
            ]);

            return back()
                ->withInput($request->only('trust'))
                ->withErrors([$usedRecovery ? 'recovery_code' : 'code' => $usedRecovery
                    ? 'That recovery code is wrong or was already used.'
                    : 'That code is wrong or has expired. Type the newest code from your app.']);
        }

        RateLimiter::clear($key);
        $fromApp = (bool) $request->session()->pull('two_factor.login')['app'];

        $response = AuthController::signIn($request, $user, $fromApp, $usedRecovery ? 'Logged in (recovery code)' : 'Logged in');

        if ($usedRecovery) {
            $left = $this->twoFactor->recoveryCodesLeft($user);
            $response->with('error', "You signed in with a recovery code. {$left} left - make new ones in Profile, and set up your app again if you lost your phone.");
        }

        if ($request->boolean('trust')) {
            $response->withCookie(TrustedDevice::trust($request, $user));
        }

        return $response;
    }

    public function showSetup()
    {
        $user = Auth::user();

        if ($user->hasTwoFactor()) {
            return redirect()->route('profile.edit');
        }

        return view('twoFactor.setup', [
            'qrSvg'  => $this->twoFactor->qrCodeSvg($user),
            'secret' => $this->twoFactor->readableSecret($user),
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $user = Auth::user();

        if ($user->hasTwoFactor()) {
            return redirect()->route('profile.edit');
        }

        $request->validate(['code' => 'required|string|max:20']);

        if (! $this->twoFactor->verify($user, $request->input('code'))) {
            return back()->withErrors(['code' => 'That code doesn\'t match. Check the app shows "' . config('two_factor.issuer') . '" for your email, and type its newest code.']);
        }

        $codes = $this->twoFactor->confirm($user);
        $this->log($request, 'Turned on two-factor sign-in');

        return redirect()->route('two-factor.recovery-codes')->with('recoveryCodes', $codes);
    }

    /** The recovery codes, once - they are stored only as hashes. */
    public function showRecoveryCodes(Request $request)
    {
        $codes = $request->session()->get('recoveryCodes');

        if (! $codes) {
            return redirect()->route('profile.edit');
        }

        return view('twoFactor.recoveryCodes', ['codes' => $codes, 'user' => Auth::user()]);
    }

    /** Profile's two-factor buttons; each needs the current password. */
    public function manage(Request $request)
    {
        $request->validate([
            'action'           => 'required|in:new-codes,forget-devices,new-phone',
            'current_password' => 'required|current_password',
        ], [
            'current_password.required' => 'Enter your current password to change two-factor sign-in.',
        ]);

        $user = Auth::user();

        switch ($request->input('action')) {
            case 'new-codes':
                $codes = $this->twoFactor->newRecoveryCodes($user);
                $this->log($request, 'Made new two-factor recovery codes');

                return redirect()->route('two-factor.recovery-codes')->with('recoveryCodes', $codes);

            case 'forget-devices':
                $user->forgetTrustedDevices();
                $this->log($request, 'Forgot trusted devices');

                return back()->with('success', 'Every trusted device will ask for a code at its next sign-in.');

            default: // new-phone
                $this->twoFactor->reset($user, signOut: false);
                $this->log($request, 'Moved two-factor sign-in to a new phone');

                return redirect()->route('two-factor.setup');
        }
    }

    /** The account whose password was right and is waiting for its code, if still in time. */
    private function waitingUser(Request $request): ?User
    {
        $waiting = $request->session()->get('two_factor.login');

        if (! $waiting || ($waiting['until'] ?? 0) < now()->timestamp) {
            return null;
        }

        $user = User::find($waiting['id']);

        return $user && $user->hasTwoFactor() && $user->isActiveOfficer() ? $user : null;
    }

    private function backToSignIn(Request $request)
    {
        $timedOut = $request->session()->has('two_factor.login');
        $fromApp = (bool) $request->session()->pull('two_factor.login.app');
        $request->session()->forget('two_factor.login');

        $redirect = redirect()->route($fromApp ? 'app.login' : 'login');

        return $timedOut ? $redirect->withErrors(['email' => 'Sign in again: the code step timed out.']) : $redirect;
    }

    private function log(Request $request, string $activity): void
    {
        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => $activity,
            'ip_address' => $request->ip(),
        ]);
    }
}
