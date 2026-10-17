{{--
    Frame for what a share link opens (ShareLinkController) - seen by people
    without an account, so it carries the SG's look (the sign-in page's) and
    nothing from inside the system.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <title>@yield('title') · QSU Student Government</title>
  {{-- What a chat app shows when the link is pasted: never the file's name. --}}
  <meta property="og:title" content="A file shared by QSU Student Government">
  <meta property="og:description" content="Open the link to view or download it.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --green-dark: #0B4128;
      --green: #1F6E4A;
      --ink: #1C3A2F;
      --muted: #6b7a75;
      --border: #E4E9E6;
      --bg: #F3F6F4;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      min-height: 100vh;
      display: flex;
      justify-content: center;
      padding: 2.5rem 1rem;
      background: var(--bg);
      color: var(--ink);
      font-family: 'Inter', system-ui, sans-serif;
    }
    .page { width: 100%; max-width: 720px; display: flex; flex-direction: column; align-items: center; }
    .page.narrow { max-width: 420px; }

    .brand { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.5rem; }
    .brand img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: var(--green-dark); }
    .brand span { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.05rem; color: var(--green-dark); }

    .card {
      width: 100%;
      padding: 1.75rem;
      border-radius: 1.25rem;
      background: #fff;
      border: 1px solid rgba(11, 65, 40, .06);
      box-shadow: 0 20px 45px -18px rgba(11, 65, 40, .22), 0 2px 8px rgba(0, 0, 0, .03);
    }

    .file-head { display: flex; align-items: center; gap: 1rem; }
    .chip {
      flex-shrink: 0; width: 52px; height: 52px;
      display: grid; place-items: center;
      border-radius: 14px; background: #eef1f0; color: #475569;
      font-size: .7rem; font-weight: 700; letter-spacing: .02em; text-transform: uppercase;
    }
    .chip.pdf   { background: #fdecec; color: #b42318; }
    .chip.doc   { background: #e8f0fe; color: #1d4ed8; }
    .chip.sheet { background: #e6f4ea; color: #046322; }
    .chip.slide { background: #fff1e6; color: #b45309; }
    .chip.image { background: #f1eafe; color: #6d28d9; }
    .file-name { font-family: 'Poppins', sans-serif; font-size: 1.15rem; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .file-meta { margin-top: .2rem; font-size: .88rem; color: var(--muted); }

    .note { margin-top: 1.25rem; padding: .85rem 1rem; border-radius: .85rem; font-size: .88rem; line-height: 1.45; }
    .note strong { display: block; }
    .note.amber { background: #fff7e6; color: #92400e; }

    .preview { display: block; width: 100%; margin-top: 1.25rem; border-radius: .85rem; border: 1px solid var(--border); background: #fafbfa; }
    img.preview { max-height: 70vh; object-fit: contain; }
    iframe.preview { height: 70vh; }

    .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; }
    .btn {
      flex: 1 1 160px;
      min-height: 50px;
      display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
      padding: 0 1.25rem;
      border-radius: .8rem;
      border: 1.5px solid var(--green);
      background: #fff; color: var(--green);
      font-size: .95rem; font-weight: 700; text-decoration: none;
    }
    .btn.primary { background: var(--green); color: #fff; box-shadow: 0 8px 18px rgba(31, 110, 74, .28); }
    .btn.primary:hover { background: var(--green-dark); border-color: var(--green-dark); }
    .btn:focus-visible { outline: 3px solid #C9A03D; outline-offset: 2px; }

    .until { margin-top: 1.25rem; font-size: .82rem; color: var(--muted); text-align: center; }

    .gone { text-align: center; }
    .gone h1 { font-family: 'Poppins', sans-serif; font-size: 1.3rem; margin-bottom: .5rem; }
    .gone p { color: var(--muted); font-size: .92rem; line-height: 1.5; }

    .footer { margin-top: 1.75rem; font-size: .78rem; color: #9AA8A3; text-align: center; }
    .footer a { color: inherit; }

    @media (max-width: 480px) {
      body { padding: 1.5rem 1rem; }
      .card { padding: 1.25rem; }
    }
  </style>
</head>
<body>
  <div class="page @yield('page-class')">
    <div class="brand">
      <img src="{{ asset('images/SG-logo.png') }}" alt="">
      <span>QSU Student Government</span>
    </div>

    @yield('content')

    <div class="footer">
      &copy; {{ date('Y') }} QSU Student Government &middot; <a href="{{ route('privacy.notice') }}">Privacy notice</a>
    </div>
  </div>
</body>
</html>
