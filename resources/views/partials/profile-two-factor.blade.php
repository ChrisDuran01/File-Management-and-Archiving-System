{{-- Profile's two-factor section (both profile pages). Each button needs the
     current password - TwoFactorController::manage(). --}}
@php
    $trustedCount = $user->trustedDevices()->where('expires_at', '>', now())->count();
    $codesLeft = count($user->two_factor_recovery_codes ?? []);
@endphp

<hr class="my-4">

<h2 class="h5 mb-2" id="two-factor">Two-factor sign-in</h2>

@if ($user->hasTwoFactor())
    <p class="text-muted mb-3">
        On since {{ $user->two_factor_confirmed_at->format('M j, Y') }}.
        {{ $codesLeft }} {{ Str::plural('recovery code', $codesLeft) }} left.
        {{ $trustedCount === 0 ? 'No trusted devices.' : $trustedCount . ' ' . Str::plural('trusted device', $trustedCount) . ' (asked for no code for 30 days).' }}
    </p>

    <form action="{{ route('two-factor.manage') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="two-factor-password" class="form-label">Current Password</label>
            <input id="two-factor-password" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="action" value="new-codes" class="btn btn-outline-success">Make new recovery codes</button>
            <button type="submit" name="action" value="forget-devices" class="btn btn-outline-secondary" @disabled($trustedCount === 0)>Forget trusted devices</button>
            <button type="submit" name="action" value="new-phone" class="btn btn-outline-secondary"
                onclick="return confirm('Set up two-factor on a new phone? Your old phone\'s codes stop working right away.');">Set up on a new phone</button>
        </div>
        <div class="form-text">New recovery codes replace the old ones. Lost your phone and your codes? Ask the Adviser or President to reset your two-factor.</div>
    </form>
@else
    <p class="text-muted mb-3">Not set up yet.</p>
    <a href="{{ route('two-factor.setup') }}" class="btn btn-success">Set up two-factor sign-in</a>
@endif
