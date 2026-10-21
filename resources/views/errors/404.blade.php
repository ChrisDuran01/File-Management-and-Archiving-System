{{-- "Page not found", for a mistyped or old address and for a file,
     folder or announcement that no longer exists. Laravel picks this file
     up by its name. Same look as the student page, where most outside
     links lead.

     An address that matches no route at all is answered before the session
     starts, so auth()->user() is null there even for a signed-in officer.
     A 404 from inside a route (a deleted file) knows who is looking. --}}
@php
    // rescue(): this page must still draw if the database is down.
    $logoPath = rescue(fn () => \App\Models\SiteSetting::current()->logo_path, null, false);
    $logo = $logoPath ? asset('storage/'.$logoPath) : asset('images/SG-logo.png');
    $student = route('student.dashboard');
    $user = auth()->user();

    if (request()->is('app', 'app/*')) {
        [$homeUrl, $homeLabel] = [route('app.home'), 'Back to the app'];
    } elseif ($user) {
        $superAdmin = rescue(fn () => \App\Models\Folder::isSuperAdmin($user), false, false);
        [$homeUrl, $homeLabel] = [url($superAdmin ? '/superAdminDashboard' : '/adminDashboard'), 'Go to your dashboard'];
    } else {
        [$homeUrl, $homeLabel] = [$student, 'Go to the student page'];
    }

    // Only a visitor gets the student page's sections to try next.
    $showQuickLinks = ! $user && ! request()->is('app', 'app/*');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="robots" content="noindex">
    <title>Page not found | QSU Student Government</title>
    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        /* The student page's tokens (Student/stuDashboard.blade.php), so the
           two read as one site. */
        :root {
            --green: #0f3d1f;
            --green-2: #145a2c;
            --green-3: #1d7a3e;
            --green-dark: #0a2a15;
            --gold: #f4b400;
            --green-soft: #e7f3ea;
            --ink: #10231a;
            --text: #34443b;
            --muted: #617067;
            --tint: #f2f7f3;
            --line: #e0e8e2;
            --line-2: #cdd8d0;
            --head: 'Poppins', 'Inter', system-ui, sans-serif;
            --radius: 16px;
            --radius-control: 4px;  /* same small corners as the student page */
            --shadow: 0 8px 26px rgba(16, 35, 26, .08);
            --shadow-lg: 0 18px 44px rgba(16, 35, 26, .14);
            color-scheme: light;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            display: flex; flex-direction: column; min-height: 100vh; min-height: 100dvh;
            background: #fff; color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px; line-height: 1.6; -webkit-font-smoothing: antialiased; overflow-x: clip;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        a, button { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
        :focus-visible { outline: 3px solid var(--green-3); outline-offset: 2px; }
        .site-footer :focus-visible { outline-color: var(--gold); }

        .wrap {
            width: 100%; max-width: 1180px; margin: 0 auto;
            padding-left: max(20px, env(safe-area-inset-left));
            padding-right: max(20px, env(safe-area-inset-right));
        }

        .site-header { height: 72px; background: #fff; box-shadow: 0 1px 0 var(--line); }
        .header-inner { height: 100%; display: flex; align-items: center; }
        .brand { display: inline-flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; }
        .brand-logo { width: 42px; height: 42px; flex-shrink: 0; border-radius: 50%; object-fit: cover; background: #fff; box-shadow: 0 0 0 2px var(--green-soft); }
        .brand-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
        .brand-text strong { color: var(--green); font-family: var(--head); font-size: 1rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .brand-text small { color: var(--muted); font-size: .7rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; }

        main { flex: 1; background: var(--tint); }
        .lost { max-width: 640px; padding-top: 56px; padding-bottom: 64px; text-align: center; }

        /* "4 (signpost) 4": the zero is a green badge with the gold ring the
           student page gives its president. */
        .code {
            display: flex; align-items: center; justify-content: center; gap: .12em;
            color: var(--green); font-family: var(--head); font-size: clamp(5.5rem, 22vw, 9rem);
            font-weight: 700; letter-spacing: -.02em; line-height: 1;
        }
        .code-o {
            display: grid; place-items: center; width: .8em; height: .8em; margin: 0 .04em; border-radius: 50%;
            background: var(--green); color: #fff; box-shadow: 0 0 0 .05em #fff, 0 0 0 .09em var(--gold), var(--shadow-lg);
        }
        .code-o .bi { font-size: .36em; line-height: 1; transform-origin: 50% 90%; animation: sway 4s ease-in-out infinite; }
        @keyframes sway { 0%, 100% { transform: rotate(-7deg); } 50% { transform: rotate(7deg); } }

        .eyebrow {
            display: inline-flex; align-items: center; gap: 10px; margin-top: 32px;
            color: var(--green-3); font-size: .78rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
        }
        .eyebrow::before, .eyebrow::after { content: ''; width: 26px; height: 3px; border-radius: 3px; background: var(--gold); }
        h1 {
            margin-top: 10px; color: var(--ink); font-family: var(--head); font-size: clamp(1.5rem, 5vw, 2.1rem);
            font-weight: 700; letter-spacing: -.015em; line-height: 1.25;
        }
        .lead { max-width: 460px; margin: 12px auto 0; color: var(--muted); font-size: 1.02rem; }

        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 30px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-height: 48px; padding: 0 24px; border: 2px solid transparent; border-radius: var(--radius-control);
            font: inherit; font-size: .95rem; font-weight: 600; text-decoration: none; cursor: pointer;
            transition: background-color .15s, border-color .15s;
        }
        .btn-primary { background: var(--green); color: #fff; }
        .btn-primary:hover { background: var(--green-2); }
        .btn-soft { background: #fff; border-color: var(--line-2); color: var(--green); }
        .btn-soft:hover { border-color: var(--green-3); background: var(--green-soft); }
        .btn[hidden] { display: none; }
        .officer-note { margin-top: 18px; color: var(--muted); font-size: .9rem; }
        .officer-note a { color: var(--green-3); font-weight: 600; text-underline-offset: 3px; }
        .officer-note a:hover { color: var(--green); }
        @media (max-width: 520px) {
            .actions .btn { flex: 1 1 100%; }
        }

        .try { max-width: 900px; padding-bottom: 72px; }
        .try-title { margin-bottom: 14px; color: var(--muted); font-size: .85rem; font-weight: 600; text-align: center; }
        .quick-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .quick-card {
            position: relative; display: flex; align-items: center; gap: 14px; padding: 18px 20px;
            border-radius: var(--radius); background: #fff; box-shadow: var(--shadow);
            color: var(--text); text-decoration: none; overflow: hidden; transition: transform .2s, box-shadow .2s;
        }
        .quick-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--gold);
            transform: scaleX(0); transform-origin: left; transition: transform .25s;
        }
        .quick-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .quick-card:hover::before { transform: scaleX(1); }
        .quick-icon {
            display: grid; place-items: center; width: 46px; height: 46px; flex-shrink: 0; border-radius: 14px;
            background: var(--green-soft); color: var(--green); font-size: 1.25rem; transition: background-color .2s, color .2s;
        }
        .quick-card:hover .quick-icon { background: var(--green); color: #fff; }
        .quick-text { display: flex; flex-direction: column; min-width: 0; }
        .quick-text strong { color: var(--ink); font-family: var(--head); font-size: 1rem; font-weight: 600; }
        .quick-text span { color: var(--muted); font-size: .85rem; line-height: 1.45; }
        @media (max-width: 760px) {
            .quick-grid { grid-template-columns: 1fr; gap: 10px; }
        }

        .site-footer { background: var(--green-dark); color: rgba(255, 255, 255, .6); font-size: .84rem; }
        .footer-row {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 20px;
            padding-top: 22px; padding-bottom: max(22px, env(safe-area-inset-bottom));
        }
        .footer-row a { display: inline-flex; align-items: center; min-height: 44px; text-decoration: none; }
        .footer-row a:hover { color: #fff; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="wrap header-inner">
        <a href="{{ $student }}" class="brand">
            <img src="{{ $logo }}" alt="" class="brand-logo" width="42" height="42">
            <span class="brand-text">
                <strong>QSU Student Government</strong>
                <small>Diffun Campus</small>
            </span>
        </a>
    </div>
</header>

<main>
    <div class="wrap lost">
        <div class="code" aria-hidden="true">
            <span>4</span>
            <span class="code-o"><i class="bi bi-signpost-split"></i></span>
            <span>4</span>
        </div>

        <p class="eyebrow">Page not found</p>
        <h1>We couldn't find that page</h1>
        <p class="lead">The link may be old or mistyped, or the page may have been moved or removed.</p>

        <div class="actions">
            <a href="{{ $homeUrl }}" class="btn btn-primary"><i class="bi bi-house" aria-hidden="true"></i> {{ $homeLabel }}</a>
            <button type="button" class="btn btn-soft" data-back hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> Go back</button>
        </div>

        @if($showQuickLinks)
            <p class="officer-note">Are you an officer? <a href="{{ route('login') }}">Sign in here</a></p>
        @endif
    </div>

    @if($showQuickLinks)
        <nav class="wrap try" aria-labelledby="try-title">
            <p class="try-title" id="try-title">Or try one of these</p>
            <div class="quick-grid">
                <a href="{{ $student }}#announcements" class="quick-card">
                    <span class="quick-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
                    <span class="quick-text"><strong>Announcements</strong><span>News, events and reminders</span></span>
                </a>
                <a href="{{ $student }}#documents" class="quick-card">
                    <span class="quick-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
                    <span class="quick-text"><strong>Documents</strong><span>Resolutions, reports and records</span></span>
                </a>
                <a href="{{ $student }}#contact" class="quick-card">
                    <span class="quick-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                    <span class="quick-text"><strong>Contact us</strong><span>Questions, concerns or ideas</span></span>
                </a>
            </div>
        </nav>
    @endif
</main>

<footer class="site-footer">
    <div class="wrap footer-row">
        <span>&copy; {{ date('Y') }} QSU Student Government · Diffun Campus</span>
        <a href="{{ route('privacy.notice') }}">Privacy notice</a>
    </div>
</footer>

<script>
    // "Go back" only when there is somewhere to go back to (not a link
    // opened in a new tab).
    (function () {
        var back = document.querySelector('[data-back]');
        if (history.length > 1) {
            back.hidden = false;
            back.addEventListener('click', function () { history.back(); });
        }
    })();
</script>
</body>
</html>
