<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password · QSU Student Government</title>
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
    <h3><i class="fas fa-key me-2"></i>Forgot your password?</h3>
    <p class="subtitle mb-4">
        Enter the email address on your account and, if it matches one we have on file, we'll send a link to reset your password.
    </p>

    @if (session('status'))
      <div class="alert alert-success" role="alert">
        {{ session('status') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
      @csrf

      <div class="mb-4">
        <label class="form-label">Email address</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus autocomplete="email">
      </div>

      <button type="submit" class="btn-change">
        <i class="fas fa-paper-plane me-1"></i> Send reset link
      </button>
    </form>

    <div class="mt-3 text-center">
      <a href="{{ route('login') }}" class="text-muted" style="font-size:0.85rem; text-decoration:none;">
        <i class="fas fa-arrow-left me-1"></i> Back to login
      </a>
    </div>
  </div>

</body>
</html>
