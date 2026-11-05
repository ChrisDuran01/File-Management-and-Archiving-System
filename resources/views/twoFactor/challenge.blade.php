@extends('twoFactor.layout')
@section('title', 'Enter your code')

@section('content')
  <h1>Enter your code</h1>
  <p class="lead">Open your authenticator app and type the 6-digit code shown for {{ config('two_factor.issuer') }}.</p>

  @if ($errors->any())
    <div class="alert" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>
  @endif

  <form method="POST" action="{{ route('two-factor.challenge.submit') }}">
    @csrf
    <label for="code">6-digit code</label>
    <input class="input code" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
           pattern="[0-9 ]*" maxlength="7" required autofocus>

    <label class="check">
      <input type="checkbox" name="trust" value="1" @checked(old('trust'))>
      <span>Trust this device for {{ config('two_factor.trust_days') }} days
        <small>Only on your own phone or computer - never on the SG office or a lab computer.</small></span>
    </label>

    <button type="submit" class="btn"><i class="fas fa-check" aria-hidden="true"></i> Sign in</button>
  </form>

  <details @if ($errors->has('recovery_code')) open @endif>
    <summary>Lost your phone? Use a recovery code</summary>
    <form method="POST" action="{{ route('two-factor.challenge.submit') }}">
      @csrf
      <label for="recovery_code">Recovery code</label>
      <input class="input" id="recovery_code" name="recovery_code" type="text" autocomplete="off" spellcheck="false"
             placeholder="XXXXX-XXXXX" required>
      <p class="lead" style="margin:.5rem 0 1rem; font-size:.8rem;">One of the 8 codes you saved when you set up two-factor. Each works once. No codes left? Ask the Adviser or President to reset your two-factor.</p>
      <button type="submit" class="btn soft">Sign in with a recovery code</button>
    </form>
  </details>

  <div class="foot">
    <a class="link-btn" href="{{ $fromApp ? route('app.login') : route('login') }}">Cancel and go back to sign-in</a>
  </div>
@endsection
