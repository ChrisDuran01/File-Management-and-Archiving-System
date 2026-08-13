<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password · QSU Student Government</title>
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
  </style>
</head>
<body>

  <div class="change-card">
    <h3><i class="fas fa-key me-2"></i>Change Your Password</h3>
    <p class="subtitle mb-4">
        For security, you must set a new password before continuing since this account was created with a temporary password.
    </p>

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('password.change.submit') }}">
      @csrf

      <div class="mb-3">
        <label class="form-label">Temporary / Current Password</label>
        <input type="password" name="current_password" class="form-control" required autofocus>
      </div>

      <div class="mb-1">
        <label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
      </div>
      <p class="text-muted mb-3" style="font-size:0.78rem;">
        At least 8 characters, with upper &amp; lower case letters, a number, and a symbol.
      </p>

      <div class="mb-4">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
      </div>

      <button type="submit" class="btn-change">
        <i class="fas fa-check me-1"></i> Update Password
      </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
      @csrf
      <button type="submit" class="btn btn-link text-muted" style="font-size:0.85rem;">
        Sign out instead
      </button>
    </form>
  </div>

</body>
</html>
