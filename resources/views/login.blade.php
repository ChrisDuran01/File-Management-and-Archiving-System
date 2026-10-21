<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>QSU Student Government · File Management & Archiving</title>
  @include('partials.favicon')
  <!-- Google Fonts: Inter + Poppins for clean modern typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <!-- Font Awesome 6 (free icons) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root {
      --qsu-green-dark: #0B4128;
      --qsu-green: #1F6E4A;
      --qsu-green-bright: #2E8B57;
      --qsu-gold: #C9A03D;
      --qsu-gold-light: #E8C468;
      --ink: #1C3A2F;
      --muted: #5b6a65;
      --border: #E4E9E6;
      --bg: #F3F6F4;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html, body {
      height: 100%;
    }

    body {
      font-family: 'Inter', sans-serif;
      color: var(--ink);
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.25rem;
      position: relative;
      overflow-x: hidden;
    }

    .page {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 400px;
      display: flex;
      flex-direction: column;
      align-items: center;
      animation: fadeInUp 0.6s ease-out;
    }

    /* brand header above the card */
    .brand-header {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      margin-bottom: 1.75rem;
    }
    .logo-circle {
      width: 76px;
      height: 76px;
      border-radius: 50%;
      background: var(--qsu-green-dark);
      overflow: hidden;
      box-shadow: 0 10px 24px rgba(11, 65, 40, 0.25);
      margin-bottom: 1rem;
    }
    /* The logo is a full-color square image, so it fills the circle as-is. */
    .logo-img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .brand-title {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.3rem;
      color: var(--qsu-green-dark);
      letter-spacing: -0.01em;
    }
    .brand-sub {
      font-size: 0.85rem;
      color: var(--muted);
      font-weight: 500;
      margin-top: 0.2rem;
    }

    /* the card */
    .login-card {
      width: 100%;
      background: #ffffff;
      border-radius: 1.25rem;
      padding: 2.25rem 2rem;
      box-shadow: 0 20px 45px -18px rgba(11, 65, 40, 0.22), 0 2px 8px rgba(0,0,0,0.03);
      border: 1px solid rgba(11, 65, 40, 0.06);
    }

    .welcome-header {
      margin-bottom: 1.5rem;
    }
    .welcome-header h3 {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.4rem;
      color: var(--ink);
      margin-bottom: 0;
    }

    /* alerts */
    .alert-custom {
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
      border-radius: 0.75rem;
      padding: 0.65rem 0.85rem;
      font-size: 0.82rem;
      font-weight: 500;
      margin-bottom: 1.1rem;
      line-height: 1.4;
    }
    .alert-danger-custom {
      background: #FDEEEE;
      color: #B3261E;
      border: 1px solid #F6D2D0;
    }
    .alert-success-custom {
      background: #EAF7EF;
      color: #1F6E4A;
      border: 1px solid #CDEBD8;
    }

    /* form fields */
    .field {
      margin-bottom: 1.15rem;
    }
    .field label {
      display: block;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 0.4rem;
      letter-spacing: 0.01em;
    }
    .field-input-wrap {
      position: relative;
    }
    .field-input-wrap i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9AA8A3;
      font-size: 0.95rem;
      transition: color 0.2s;
      pointer-events: none;
    }
    .field-input-wrap input {
      width: 100%;
      height: 48px;
      padding: 0 0.9rem 0 2.6rem;
      border-radius: 0.7rem;
      border: 1.5px solid var(--border);
      background: #FBFCFB;
      font-family: 'Inter', sans-serif;
      font-size: 0.92rem;
      font-weight: 500;
      color: var(--ink);
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .field-input-wrap input::placeholder {
      color: #6b7a75;
      font-weight: 400;
    }
    .field-input-wrap input:focus {
      outline: none;
      border-color: var(--qsu-green-bright);
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(46, 139, 87, 0.12);
    }
    .field-input-wrap input:focus ~ i {
      color: var(--qsu-green);
    }

    /* show/hide password button - the icon rule above targets every <i> in the wrap, so it is overridden here */
    .field-input-wrap .pw-toggle {
      position: absolute;
      right: 0.35rem;
      top: 50%;
      transform: translateY(-50%);
      width: 38px;
      height: 38px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: none;
      border: none;
      border-radius: 0.5rem;
      cursor: pointer;
      color: #6b7a75;
    }
    .field-input-wrap .pw-toggle:hover,
    .field-input-wrap .pw-toggle:focus-visible { color: var(--qsu-green); outline: none; }
    .field-input-wrap .pw-toggle i {
      position: static;
      transform: none;
      pointer-events: none;
      color: inherit;
    }

    .row-between {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin-bottom: 1.4rem;
    }
    .forgot-link {
      color: var(--qsu-green);
      text-decoration: none;
      font-size: 0.8rem;
      font-weight: 600;
      transition: color 0.2s;
    }
    .forgot-link:hover {
      color: var(--qsu-green-dark);
    }

    .btn-login {
      width: 100%;
      background: var(--qsu-green);
      border: none;
      border-radius: 0.75rem;
      padding: 0.85rem;
      font-family: 'Inter', sans-serif;
      font-weight: 700;
      font-size: 0.95rem;
      color: white;
      cursor: pointer;
      box-shadow: 0 8px 18px rgba(31, 110, 74, 0.28);
      transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.55rem;
    }
    .btn-login:hover {
      background: var(--qsu-green-dark);
      transform: translateY(-1px);
      box-shadow: 0 12px 22px rgba(31, 110, 74, 0.35);
    }
    .btn-login:active {
      transform: translateY(0);
    }
    .btn-login:disabled {
      background: #A9B3AF;
      box-shadow: none;
      cursor: not-allowed;
      transform: none;
    }

    .signup-text {
      margin-top: 1.5rem;
      text-align: center;
      font-size: 0.83rem;
      color: var(--muted);
    }
    .signup-text a {
      color: var(--qsu-green);
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px solid rgba(31, 110, 74, 0.3);
    }
    .signup-text a:hover {
      color: var(--qsu-green-dark);
      border-bottom-color: var(--qsu-gold);
    }

    .page-footer {
      margin-top: 1.75rem;
      text-align: center;
      font-size: 0.75rem;
      color: #5b6a65;
      font-weight: 500;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(16px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 420px) {
      .login-card {
        padding: 1.75rem 1.4rem;
      }
      body {
        padding: 1.5rem 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="brand-header">
      <div class="logo-circle">
        <img src="{{ asset('images/SG-logo.png') }}" alt="" class="logo-img" onerror="this.onerror=null; this.src='https://placehold.co/46x46/ffffff/1F6E4A?text=SG';">
      </div>
      <div class="brand-title">QSU Student Government</div>
      <div class="brand-sub">File Management &amp; Archiving System</div>
    </div>

    <div class="login-card">
      <div class="welcome-header">
        <h3>Sign in</h3>
      </div>

      {{-- Also shown at the officer phone app's address (/app/login) - see
           OfficerAppController::showLogin(). A phone goes on to the app
           after signing in either way (AuthController::login()). --}}
      <form method="POST" action="{{ url('/login') }}">
        @csrf
        @if (! empty($fromApp))
          <input type="hidden" name="from_app" value="1">
        @endif

        @if ($errors->any())
          <div class="alert-custom alert-danger-custom" role="alert" id="loginAlert">
            <i class="fas fa-circle-exclamation mt-1"></i>
            <span id="loginAlertText">{{ $errors->first() }}</span>
          </div>
        @endif

        @if (session('success'))
          <div class="alert-custom alert-success-custom" role="alert">
            <i class="fas fa-circle-check mt-1"></i>
            <span>{{ session('success') }}</span>
          </div>
        @endif

        <div class="field">
          <label for="loginEmail">Email address</label>
          <div class="field-input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" id="loginEmail" placeholder="you@example.com" required autocomplete="email">
          </div>
        </div>

        <div class="field">
          <label for="loginPassword">Password</label>
          <div class="field-input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password" style="padding-right:2.8rem;">
            <button type="button" class="pw-toggle" id="togglePassword" aria-label="Show password" aria-pressed="false" tabindex="0">
              <i class="fas fa-eye" id="togglePasswordIcon"></i>
            </button>
          </div>
        </div>

        <div class="row-between">
          <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login" id="loginSubmitBtn">
          <i class="fas fa-sign-in-alt"></i> <span id="loginSubmitBtnText">Log in</span>
        </button>

        <div class="signup-text">
          By signing in you acknowledge our
          <a href="{{ route('privacy.notice') }}" id="openPrivacy">Privacy Notice</a>.
        </div>
      </form>
    </div>

    <div class="page-footer">
      &copy; {{ date('Y') }} QSU Student Government &middot; Digitizing governance, one record at a time.
    </div>
  </div>

<script>
  (function () {
    var input = document.getElementById('loginPassword');
    var btn   = document.getElementById('togglePassword');
    var icon  = document.getElementById('togglePasswordIcon');
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      input.focus();
    });
  })();
</script>

{{-- Privacy notice popup. The link above still works without JavaScript (it goes to /privacy). --}}
<style>
  #privacyDialog { border:1px solid #e5e5e5; border-radius:12px; padding:0; width:min(560px, calc(100% - 32px)); max-height:calc(100vh - 32px); background:#fff; color:#111; margin:auto; overflow:hidden; }
  #privacyDialog::backdrop { background:rgba(0,0,0,.45); }
  #privacyDialog .pd-body { display:flex; flex-direction:column; max-height:calc(100vh - 34px); }
  /* only the text area scrolls; freeze the page behind while the popup is open */
  html:has(#privacyDialog[open]) { overflow:hidden; }
  #privacyDialog h2.pd-title { font-size:1.1rem; font-weight:600; padding:22px 24px 4px; }
  #privacyDialog .pd-sub { color:#6b6b6b; font-size:.8rem; padding:0 24px 12px; }
  #privacyDialog .pd-scroll { flex:1 1 auto; min-height:0; overflow-y:auto; padding:14px 24px; border-top:1px solid #e5e5e5; border-bottom:1px solid #e5e5e5; }
  #privacyDialog .pd-actions { padding:14px 24px; text-align:right; }
  #privacyDialog .pd-close { background:#1F6E4A; color:#fff; border:1px solid #1F6E4A; border-radius:8px; padding:8px 18px; font:inherit; font-size:.86rem; font-weight:500; cursor:pointer; }
  #privacyDialog, #privacyDialog .pd-scroll { color-scheme:light; }
  #privacyDialog, #privacyDialog button { font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','SF Pro Display','Helvetica Neue',Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased; }
  /* simple thin scrollbar */
  #privacyDialog .pd-scroll { scrollbar-width:thin; scrollbar-color:#c8c8c8 transparent; }
  #privacyDialog .pd-scroll::-webkit-scrollbar { width:6px; }
  #privacyDialog .pd-scroll::-webkit-scrollbar-track { background:transparent; }
  #privacyDialog .pd-scroll::-webkit-scrollbar-thumb { background:#c8c8c8; border-radius:3px; }
  #privacyDialog .pd-scroll::-webkit-scrollbar-thumb:hover { background:#a8a8a8; }
</style>
<dialog id="privacyDialog" aria-labelledby="pdTitle">
  <div class="pd-body">
    <h2 class="pd-title" id="pdTitle">Privacy Notice</h2>
    <p class="pd-sub">Version {{ config('privacy.version') }} &middot; Data Privacy Act of 2012 (RA 10173)</p>
    <div class="pd-scroll">
      @include('partials.privacy-content')
    </div>
    <div class="pd-actions">
      <button type="button" class="pd-close" id="closePrivacy">Close</button>
    </div>
  </div>
</dialog>
<script>
  (function () {
    var dlg = document.getElementById('privacyDialog');
    var link = document.getElementById('openPrivacy');
    if (!dlg || !link || typeof dlg.showModal !== 'function') return;
    link.addEventListener('click', function (e) { e.preventDefault(); dlg.showModal(); });
    document.getElementById('closePrivacy').addEventListener('click', function () { dlg.close(); });
    // click on the dimmed backdrop closes it too
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  })();
</script>

@if (session('lockout_seconds'))
<script>
  (function() {
    let remaining = {{ (int) session('lockout_seconds') }};

    const textEl = document.getElementById('loginAlertText');
    const submitBtn = document.getElementById('loginSubmitBtn');
    const submitBtnText = document.getElementById('loginSubmitBtnText');

    function render() {
      if (remaining <= 0) {
        textEl.textContent = 'You can try again now.';
        submitBtn.disabled = false;
        submitBtnText.textContent = 'Log in';
        return;
      }

      const unit = remaining === 1 ? 'second' : 'seconds';
      textEl.textContent = `Too many login attempts. Please try again in ${remaining} ${unit}.`;
      submitBtn.disabled = true;
      submitBtnText.textContent = `Try again in ${remaining}s`;

      remaining--;
      setTimeout(render, 1000);
    }

    render();
  })();
</script>
@endif
</body>
</html>
