@extends('twoFactor.layout')
@section('title', 'Set up two-factor sign-in')
@section('width', '480px')

@section('styles')
    .steps { list-style: none; counter-reset: step; margin: 0; }
    .steps > li { counter-increment: step; position: relative; padding-left: 2.1rem; margin-bottom: 1.25rem; font-size: .9rem; }
    .steps > li::before {
      content: counter(step); position: absolute; left: 0; top: 0;
      width: 1.45rem; height: 1.45rem; border-radius: 4px; background: #E7F3EA; color: var(--qsu-green);
      font-weight: 700; font-size: .8rem; display: flex; align-items: center; justify-content: center;
    }
    .steps strong { display: block; margin-bottom: .2rem; }
    .muted { color: var(--muted); font-size: .84rem; }
    .qr { display: flex; justify-content: center; margin: .75rem 0; }
    .qr svg { width: 200px; height: 200px; border: 1px solid var(--border); border-radius: .6rem; padding: 6px; background: #fff; }
    .key { font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-size: .95rem; letter-spacing: .04em;
           background: #F3F6F4; border: 1px solid var(--border); border-radius: .5rem; padding: .5rem .7rem; overflow-wrap: anywhere; margin-top: .35rem; }
@endsection

@section('content')
  <h1>Set up two-factor sign-in</h1>
  <p class="lead">Every account now needs a code from an authenticator app when signing in, on top of the password. This takes about a minute.</p>

  @if ($errors->any())
    <div class="alert" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>
  @endif

  <ol class="steps">
    <li>
      <strong>Get an authenticator app on your phone</strong>
      <span class="muted">Google Authenticator or Microsoft Authenticator (free). On an iPhone you can also use Settings → Passwords, with nothing to download.</span>
    </li>
    <li>
      <strong>Scan this QR code with the app</strong>
      <div class="qr" role="img" aria-label="QR code for {{ config('two_factor.issuer') }}, {{ auth()->user()->email }}">{!! $qrSvg !!}</div>
      <span class="muted">Can't scan it? Add an account in the app by hand and type this key ({{ auth()->user()->email }}, time-based):</span>
      <div class="key">{{ $secret }}</div>
    </li>
    <li>
      <strong>Type the 6-digit code the app shows</strong>
      <form method="POST" action="{{ route('two-factor.setup.confirm') }}" style="margin-top:.5rem;">
        @csrf
        <label for="code" class="sr-only">6-digit code</label>
        <input class="input code" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9 ]*" maxlength="7" required>
        <button type="submit" class="btn" style="margin-top:.85rem;"><i class="fas fa-shield-halved" aria-hidden="true"></i> Turn on two-factor</button>
      </form>
    </li>
  </ol>

  <div class="note">
    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
    <span>Use your own phone. An authenticator extension on your own computer also works, but never set this up on the SG office computer or a lab computer: anyone using it could make your codes.</span>
  </div>

  <form method="POST" action="{{ route('logout') }}" class="foot">
    @csrf
    <button type="submit" class="link-btn">Sign out instead</button>
  </form>
@endsection
