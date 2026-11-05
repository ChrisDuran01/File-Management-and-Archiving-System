{{-- "Too many visits", when one IP address goes over a public page's limit
     per minute (config/throttle.php). Laravel picks this file up by its name.
     Same look as 404.blade.php and the student page; the Try again button
     waits out the Retry-After seconds first. --}}
@php
    // rescue(): this page must still draw if the database is down.
    $logoPath = rescue(fn () => \App\Models\SiteSetting::current()->logo_path, null, false);
    $logo = $logoPath ? asset('storage/'.$logoPath) : asset('images/SG-logo.png');
    $wait = max(1, (int) (isset($exception) ? ($exception->getHeaders()['Retry-After'] ?? 60) : 60));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="robots" content="noindex">
    <title>Too many visits | QSU Student Government</title>
    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        /* The student page's tokens, as in 404.blade.php. */
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
            --head: 'Poppins', 'Inter', system-ui, sans-serif;
            --radius-control: 4px;
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
        .busy { max-width: 640px; padding-top: 56px; padding-bottom: 72px; text-align: center; }

        /* The 404's green badge with the gold ring, holding an hourglass. */
        .badge {
            display: grid; place-items: center; width: 112px; height: 112px; margin: 0 auto; border-radius: 50%;
            background: var(--green); color: #fff; font-size: 2.6rem;
            box-shadow: 0 0 0 6px #fff, 0 0 0 11px var(--gold), var(--shadow-lg);
        }
        .badge .bi { animation: turn 3s ease-in-out infinite; }
        @keyframes turn { 0%, 40% { transform: rotate(0); } 50%, 90% { transform: rotate(180deg); } 100% { transform: rotate(360deg); } }

        .eyebrow {
            display: inline-flex; align-items: center; gap: 10px; margin-top: 36px;
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
        .btn-primary[aria-disabled="true"] { opacity: .55; pointer-events: none; }
        @media (max-width: 520px) {
            .actions .btn { flex: 1 1 100%; }
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
        <a href="{{ route('student.dashboard') }}" class="brand">
            <img src="{{ $logo }}" alt="" class="brand-logo" width="42" height="42">
            <span class="brand-text">
                <strong>QSU Student Government</strong>
                <small>Diffun Campus</small>
            </span>
        </a>
    </div>
</header>

<main>
    <div class="wrap busy">
        <div class="badge" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>

        <p class="eyebrow">Too many requests</p>
        <h1>Too many visits at once</h1>
        <p class="lead">Your network opened pages here too many times in the last minute. Wait <span data-wait>{{ $wait }}</span> seconds, then try again.</p>

        <div class="actions">
            <a href="{{ request()->fullUrl() }}" class="btn btn-primary" data-retry><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Try again</a>
        </div>
    </div>
</main>

<footer class="site-footer">
    <div class="wrap footer-row">
        <span>&copy; {{ date('Y') }} QSU Student Government · Diffun Campus</span>
        <a href="{{ route('privacy.notice') }}">Privacy notice</a>
    </div>
</footer>

<script>
    // Count the wait down, and keep Try again from asking again too soon.
    (function () {
        var left = {{ $wait }};
        var count = document.querySelector('[data-wait]');
        var retry = document.querySelector('[data-retry]');
        retry.setAttribute('aria-disabled', 'true');

        var timer = setInterval(function () {
            left -= 1;
            count.textContent = Math.max(left, 0);
            if (left <= 0) {
                clearInterval(timer);
                retry.removeAttribute('aria-disabled');
            }
        }, 1000);
    })();
</script>
</body>
</html>
