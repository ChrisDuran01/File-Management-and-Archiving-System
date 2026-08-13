<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password · QSU Student Government</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #0B4128 0%, #1C6E4A 50%, #0F5C3E 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }
    .change-card {
      width: 100%;
      max-width: 460px;
      background: white;
      border-radius: 1.5rem;
      padding: 2.2rem;
      box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.3);
    }
    .change-card h3 {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      color: #1C3A2F;
    }
    .change-card p.subtitle {
      color: #6c757d;
      font-size: 0.9rem;
    }
    .btn-change {
      background: linear-gradient(95deg, #1F6E4A 0%, #2E8B57 100%);
      border: none;
      border-radius: 2rem;
      padding: 0.75rem;
      font-weight: 700;
      color: white;
      width: 100%;
    }
    .btn-change:hover { color: white; }
    .req-list {
      list-style: none;
      padding: 0;
      margin: 0.5rem 0 1.25rem;
      font-size: 0.8rem;
    }
    .req-list li {
      color: #9ca3af;
      margin-bottom: 0.2rem;
      transition: color 0.15s;
    }
    .req-list li.met {
      color: #1F6E4A;
      font-weight: 600;
    }
    .req-list li i {
      width: 1.1rem;
      display: inline-block;
    }
  </style>
</head>
<body>

  <div class="change-card">
    <h3><i class="fas fa-lock-open me-2"></i>Reset Your Password</h3>
    <p class="subtitle mb-4">Choose a new password for your account.</p>

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" id="resetForm">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">

      <div class="mb-3">
        <label class="form-label">Email address</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required autofocus autocomplete="email">
      </div>

      <div class="mb-2">
        <label class="form-label">New Password</label>
        <input type="password" name="password" id="newPassword" class="form-control" required minlength="8" autocomplete="new-password">
      </div>

      <ul class="req-list" id="reqList">
        <li data-rule="length"><i class="fas fa-circle-notch"></i> At least 8 characters</li>
        <li data-rule="mixedCase"><i class="fas fa-circle-notch"></i> Upper &amp; lower case letters</li>
        <li data-rule="number"><i class="fas fa-circle-notch"></i> At least one number</li>
        <li data-rule="symbol"><i class="fas fa-circle-notch"></i> At least one symbol</li>
      </ul>

      <div class="mb-4">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
      </div>

      <button type="submit" class="btn-change">
        <i class="fas fa-check me-1"></i> Reset Password
      </button>
    </form>
  </div>

  <script>
    const newPassword = document.getElementById('newPassword');
    const rules = {
      length: v => v.length >= 8,
      mixedCase: v => /[a-z]/.test(v) && /[A-Z]/.test(v),
      number: v => /[0-9]/.test(v),
      symbol: v => /[^A-Za-z0-9]/.test(v),
    };

    newPassword.addEventListener('input', function () {
      const value = this.value;
      Object.keys(rules).forEach(function (rule) {
        const li = document.querySelector('[data-rule="' + rule + '"]');
        const met = rules[rule](value);
        li.classList.toggle('met', met);
        li.querySelector('i').className = met ? 'fas fa-check-circle' : 'fas fa-circle-notch';
      });
    });
  </script>

</body>
</html>
