<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>QSU Student Government · File Management & Archiving</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
  <!-- Google Fonts: Inter + Poppins for clean modern typography -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome 6 (free icons) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #F4F7FC;
      height: 100vh;
      overflow: hidden;
    }

    /* Split screen layout — no scroll on body */
    .split-layout {
      display: flex;
      width: 100%;
      height: 100vh;
      overflow: hidden;
    }

    /* LEFT SIDE — BRANDING HERO */
    .brand-side {
      flex: 1.1;
      background: linear-gradient(135deg, #0B4128 0%, #1C6E4A 50%, #0F5C3E 100%);
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      box-shadow: 8px 0 30px rgba(0, 0, 0, 0.1);
      z-index: 1;
    }

    /* abstract organic shapes & orbs (wow factor) */
    .brand-side::before {
      content: "";
      position: absolute;
      width: 180%;
      height: 180%;
      background: radial-gradient(circle at 20% 40%, rgba(255,215,130,0.15) 0%, rgba(46,139,86,0.1) 70%);
      top: -40%;
      left: -40%;
      border-radius: 50%;
      pointer-events: none;
    }

    .brand-side::after {
      content: "";
      position: absolute;
      width: 100%;
      height: 100%;
      background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" opacity="0.08"><path fill="%23FFE484" d="M47.5,57.5c-4.2,0-8-3-8-7.5s3.8-7.5,8-7.5s8,3,8,7.5S51.7,57.5,47.5,57.5z M152.5,57.5c-4.2,0-8-3-8-7.5s3.8-7.5,8-7.5s8,3,8,7.5S156.7,57.5,152.5,57.5z M100,32.5c-4.2,0-8-3-8-7.5s3.8-7.5,8-7.5s8,3,8,7.5S104.2,32.5,100,32.5z M100,115c-4.2,0-8-3-8-7.5s3.8-7.5,8-7.5s8,3,8,7.5S104.2,115,100,115z M75,71c-4.2,0-8-3-8-7.5s3.8-7.5,8-7.5s8,3,8,7.5S79.2,71,75,71z M125,71c-4.2,0-8-3-8-7.5s3.8-7.5,8-7.5s8,3,8,7.5S129.2,71,125,71z"/></svg>') repeat;
      background-size: 28px;
      opacity: 0.1;
      pointer-events: none;
    }

    .brand-content {
      position: relative;
      z-index: 2;
      padding: 2.5rem;
      max-width: 500px;
      text-align: center;
      animation: fadeInUp 0.8s ease-out;
    }

    /* logo area big and prominent */
    .logo-master {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .logo-circle-large {
      background: rgba(255, 255, 245, 0.15);
      backdrop-filter: blur(6px);
      width: 130px;
      height: 130px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid rgba(255, 245, 180, 0.6);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
      transition: transform 0.3s ease;
    }
    .logo-circle-large:hover {
      transform: scale(1.02);
    }
    .logo-img {
      max-width: 90px;
      max-height: 90px;
      width: auto;
      height: auto;
      object-fit: contain;
      filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
    }
    .brand-title {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 2.2rem;
      letter-spacing: -0.02em;
      color: white;
      text-shadow: 0 2px 5px rgba(0,0,0,0.1);
      margin-bottom: 0.5rem;
    }
    .brand-sub {
      font-size: 1rem;
      color: rgba(255,255,210,0.9);
      font-weight: 500;
      max-width: 350px;
      margin: 0 auto;
      line-height: 1.4;
    }
    .system-tagline {
      margin-top: 2rem;
      background: rgba(0,0,0,0.2);
      backdrop-filter: blur(4px);
      border-radius: 60px;
      padding: 0.6rem 1.2rem;
      display: inline-block;
      font-size: 0.85rem;
      font-weight: 500;
      color: #FFE9B6;
      letter-spacing: 0.3px;
    }
    .system-tagline i {
      margin-right: 6px;
    }
    .decor-quote {
      margin-top: 3rem;
      font-size: 0.9rem;
      font-style: italic;
      color: rgba(255,250,210,0.7);
      border-left: 2px solid #FFE484;
      padding-left: 1rem;
      text-align: left;
    }

    /* RIGHT SIDE — LOGIN FORM (modern glass) */
    .form-side {
      flex: 0.9;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow-y: auto;
      padding: 2rem;
    }
    /* subtle wave overlay */
    .form-side::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(ellipse at 80% 20%, rgba(27, 110, 67, 0.03) 0%, transparent 70%);
      pointer-events: none;
    }

    .login-card {
      width: 100%;
      max-width: 460px;
      background: white;
      border-radius: 2rem;
      padding: 2rem 2rem 2.2rem;
      box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.2), 0 1px 2px rgba(0,0,0,0.02);
      transition: transform 0.2s ease;
      border: 1px solid rgba(0,0,0,0.05);
    }

    .welcome-header h3 {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.8rem;
      color: #1C3A2F;
      margin-bottom: 0.25rem;
    }
    .welcome-header p {
      color: #6c757d;
      font-size: 0.9rem;
      font-weight: 400;
    }
    .divider-light {
      width: 60px;
      height: 3px;
      background: linear-gradient(90deg, #2C8C5A, #C9A03D);
      border-radius: 4px;
      margin: 0.75rem 0 1.5rem 0;
    }

    /* modern input group style */
    .input-group-custom {
      margin-bottom: 1.5rem;
      position: relative;
    }
    .input-group-custom .input-icon {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: #8F9B9A;
      font-size: 1.1rem;
      z-index: 2;
      transition: color 0.2s;
    }
    .input-group-custom .form-control {
      height: 56px;
      padding: 0.5rem 1rem 0.5rem 48px;
      border-radius: 1.2rem;
      border: 1.5px solid #E2E8F0;
      background: #FEFEFE;
      font-size: 0.95rem;
      font-weight: 500;
      transition: all 0.25s;
      color: #1f2e2a;
    }
    .input-group-custom .form-control:focus {
      border-color: #2C8C5A;
      box-shadow: 0 0 0 4px rgba(44, 140, 90, 0.15);
      outline: none;
    }
    .input-group-custom .form-control:focus + .input-icon {
      color: #2C8C5A;
    }
    .form-label-floating {
      position: absolute;
      left: 48px;
      top: 50%;
      transform: translateY(-50%);
      background: white;
      padding: 0 6px;
      font-size: 0.95rem;
      color: #95a5a6;
      pointer-events: none;
      transition: 0.2s ease all;
    }
    .input-group-custom .form-control:focus ~ .form-label-floating,
    .input-group-custom .form-control:not(:placeholder-shown) ~ .form-label-floating {
      top: -0px;
      transform: translateY(-50%);
      font-size: 0.7rem;
      left: 45px;
      color: #2C8C5A;
      background: white;
      font-weight: 600;
    }

    /* forgot & button */
    .forgot-link {
      color: #58856E;
      text-decoration: none;
      font-size: 0.8rem;
      font-weight: 500;
      transition: 0.2s;
    }
    .forgot-link:hover {
      color: #C9A03D;
      text-decoration: underline;
    }
    .btn-login {
      background: linear-gradient(95deg, #1F6E4A 0%, #2E8B57 100%);
      border: none;
      border-radius: 2rem;
      padding: 0.8rem;
      font-weight: 700;
      font-size: 1rem;
      color: white;
      box-shadow: 0 6px 14px rgba(31, 110, 74, 0.25);
      transition: all 0.25s;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }
    .btn-login:hover {
      transform: translateY(-2px);
      background: linear-gradient(95deg, #2A7F56 0%, #3D9B6E 100%);
      box-shadow: 0 12px 22px rgba(31, 110, 74, 0.35);
    }
    .btn-login:active {
      transform: translateY(1px);
    }
    .btn-login:disabled {
      background: #9ca3af;
      box-shadow: none;
      cursor: not-allowed;
      transform: none;
    }

    /* extra footer */
    .signup-text {
      font-size: 0.85rem;
      color: #5b6e6a;
    }
    .signup-text a {
      color: #1F6E4A;
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px solid #cbdcd2;
    }
    .signup-text a:hover {
      color: #C9A03D;
      border-bottom-color: #C9A03D;
    }

    /* responsive behavior: if screen becomes too narrow, adjust ratio */
    @media (max-width: 992px) {
      .brand-title {
        font-size: 1.7rem;
      }
      .logo-circle-large {
        width: 100px;
        height: 100px;
      }
      .logo-img {
        max-width: 68px;
      }
      .brand-content {
        padding: 1rem;
      }
    }
    @media (max-width: 768px) {
      .split-layout {
        flex-direction: column;
      }
      .brand-side {
        flex: 0.6;
        min-height: 38vh;
      }
      .form-side {
        flex: 1;
        padding: 1.5rem;
      }
      body {
        overflow: auto;
        height: auto;
      }
      .split-layout {
        height: auto;
        min-height: 100vh;
      }
      .brand-content {
        padding: 1.5rem;
      }
      .decor-quote {
        display: none;
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* show placeholder style */
    input::-webkit-input-placeholder {
      color: transparent;
    }
    input:-moz-placeholder {
      color: transparent;
    }
    /* ensure autofill background matches */
    input:-webkit-autofill,
    input:-webkit-autofill:focus {
      transition: background-color 600000s 0s, color 600000s 0s;
      -webkit-text-fill-color: #1f2e2a;
    }
  </style>
</head>
<body>
<div class="split-layout">
  <!-- LEFT SIDE: BRANDING + LOGO + QSU Student Government and file system info -->
  <div class="brand-side">
    <div class="brand-content">
      <div class="logo-master">
        <div class="logo-circle-large">
          <!-- SG-logo.png inside images folder -->
          <img src="images/SG-logo.png" alt="SG Logo" class="logo-img" onerror="this.onerror=null; this.src='https://placehold.co/130x130?text=SG';">
        </div>
        <div>
          <h1 class="brand-title">QSU Student Government</h1>
          <div class="brand-sub">Empowering Leaders, Preserving Legacy</div>
        </div>
      </div>
      <div class="system-tagline">
        <i class="fas fa-folder-open"></i> File Management & Archiving System
        <i class="fas fa-archive ms-2"></i>
      </div>
      <div class="decor-quote">
        <i class="fas fa-quote-left me-1" style="opacity:0.8;"></i> Digitizing governance, one record at a time.
      </div>
    </div>
  </div>

  <!-- RIGHT SIDE: LOGIN FORM -->
  <div class="form-side">
    <div class="login-card">
      <div class="welcome-header">
        <h3>Welcome back</h3>
        <p>Sign in to access the System</p>
        <div class="divider-light"></div>
      </div>

      <form method="POST" action="/login">
        @csrf

        @if ($errors->any())
          <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem;" role="alert" id="loginAlert">
            <span id="loginAlertText">{{ $errors->first() }}</span>
          </div>
        @endif

        <!-- Email input with icon + floating label -->
        <div class="input-group-custom">
          <i class="fas fa-envelope input-icon"></i>
          <input type="email" name="email" id="loginEmail" class="form-control" placeholder=" " required autocomplete="email">
          <label for="loginEmail" class="form-label-floating">Email address</label>
        </div>

        <!-- Password input with icon + floating label -->
        <div class="input-group-custom">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="loginPassword" class="form-control" placeholder=" " required autocomplete="current-password">
          <label for="loginPassword" class="form-label-floating">Password</label>
        </div>

        <!-- Forgot password row -->
        <div class="d-flex justify-content-end mb-4">
          <a href="{{ route('password.request') }}" class="forgot-link"><i class="fas fa-key me-1"></i> Forgot password?</a>
        </div>

        <!-- Login Button with arrow icon -->
        <button type="submit" class="btn-login" id="loginSubmitBtn">
          <i class="fas fa-sign-in-alt"></i> <span id="loginSubmitBtnText">Log in</span>
        </button>

        <!-- Additional help text -->
        <div class="mt-4 text-center signup-text">
          Don't have an account? <a href="#">Request access</a>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- small script to handle floating label behavior if autofill exists -->
<script>
  (function() {
    const inputs = document.querySelectorAll('.input-group-custom input');
    inputs.forEach(input => {
      if (input.value.trim() !== '') {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.setAttribute('data-filled', 'true');
      }
      input.addEventListener('input', function() {
        if (this.value.length > 0) {
          this.setAttribute('data-filled', 'true');
        } else {
          this.removeAttribute('data-filled');
        }
      });
      // handle browser autofill detection
      const observer = new MutationObserver(function(mutations) {
        if (input.value && input.value.length > 0) {
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
      observer.observe(input, { attributes: true, attributeFilter: ['value'] });
    });
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

<!-- optional bootstrap bundle (for proper interactions) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>
</html>