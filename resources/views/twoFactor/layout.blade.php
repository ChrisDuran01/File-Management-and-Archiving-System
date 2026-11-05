{{-- The two-factor pages (code, setup, recovery codes): the sign-in page's
     look (login.blade.php), since they are the steps right after it. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>@yield('title') · QSU Student Government</title>
  @include('partials.favicon')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root {
      --qsu-green-dark: #0B4128;
      --qsu-green: #1F6E4A;
      --qsu-green-bright: #2E8B57;
      --ink: #1C3A2F;
      --muted: #5b6a65;
      --border: #E4E9E6;
      --bg: #F3F6F4;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      color: var(--ink);
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      line-height: 1.5;
    }
    .page { width: 100%; max-width: @yield('width', '420px'); }
    .brand { display: flex; align-items: center; gap: .75rem; justify-content: center; margin-bottom: 1.25rem; }
    .brand img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: var(--qsu-green-dark); }
    .brand strong { font-family: 'Poppins', sans-serif; font-size: 1.05rem; color: var(--qsu-green-dark); }
    .card {
      background: #fff;
      border-radius: 1.25rem;
      padding: 2rem 1.75rem;
      box-shadow: 0 20px 45px -18px rgba(11, 65, 40, .22), 0 2px 8px rgba(0, 0, 0, .03);
      border: 1px solid rgba(11, 65, 40, .06);
    }
    h1 { font-family: 'Poppins', sans-serif; font-size: 1.35rem; font-weight: 700; margin-bottom: .4rem; }
    .lead { color: var(--muted); font-size: .9rem; margin-bottom: 1.25rem; }
    .alert {
      display: flex; gap: .5rem; align-items: flex-start;
      border-radius: .75rem; padding: .65rem .85rem; font-size: .84rem; font-weight: 500; margin-bottom: 1.1rem;
      background: #FDEEEE; color: #B3261E; border: 1px solid #F6D2D0;
    }
    .alert i { margin-top: .2rem; }
    .note {
      display: flex; gap: .55rem; align-items: flex-start;
      background: #FFF8E6; border: 1px solid #F1E2B5; color: #6B5310;
      border-radius: .75rem; padding: .7rem .85rem; font-size: .82rem; margin: 1rem 0;
    }
    .note i { margin-top: .2rem; }
    label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: .4rem; }
    .input {
      width: 100%; height: 48px; padding: 0 .9rem;
      border-radius: .7rem; border: 1.5px solid var(--border); background: #FBFCFB;
      font-family: inherit; font-size: 1rem; font-weight: 500; color: var(--ink);
    }
    .input.code { font-size: 1.4rem; letter-spacing: .35em; text-align: center; font-variant-numeric: tabular-nums; }
    .input:focus { outline: none; border-color: var(--qsu-green-bright); background: #fff; box-shadow: 0 0 0 4px rgba(46, 139, 87, .12); }
    .check { display: flex; gap: .55rem; align-items: flex-start; margin: 1rem 0 1.25rem; font-size: .86rem; font-weight: 500; }
    .check input { width: 18px; height: 18px; margin-top: .1rem; accent-color: var(--qsu-green); flex-shrink: 0; }
    .check small { display: block; color: var(--muted); font-weight: 400; }
    .btn {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: .5rem;
      background: var(--qsu-green); color: #fff; border: none; border-radius: .75rem;
      padding: .85rem; font-family: inherit; font-weight: 700; font-size: .95rem; cursor: pointer; text-decoration: none;
    }
    .btn:hover { background: var(--qsu-green-dark); }
    .btn.soft { background: #fff; color: var(--qsu-green); border: 1.5px solid var(--border); }
    .btn.soft:hover { border-color: var(--qsu-green-bright); background: #F3F9F5; }
    .link-btn { background: none; border: none; color: var(--muted); font-family: inherit; font-size: .84rem; cursor: pointer; text-decoration: underline; }
    .link-btn:hover { color: var(--qsu-green); }
    .foot { margin-top: 1.1rem; text-align: center; }
    details { margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem; }
    summary { cursor: pointer; font-size: .86rem; font-weight: 600; color: var(--qsu-green); }
    details[open] summary { margin-bottom: .85rem; }
    :focus-visible { outline: 3px solid var(--qsu-green-bright); outline-offset: 2px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    @yield('styles')
  </style>
</head>
<body>
  <main class="page">
    <div class="brand">
      <img src="{{ asset('images/SG-logo.png') }}" alt="">
      <strong>QSU Student Government</strong>
    </div>
    <div class="card">
      @yield('content')
    </div>
  </main>
  @yield('scripts')
</body>
</html>
