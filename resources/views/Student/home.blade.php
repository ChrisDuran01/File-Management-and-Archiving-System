<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f3d1f">
    {{-- All three pages on this layout have a dark mode; the script below sets this to the one in use. --}}
    <meta name="color-scheme" content="light dark">
    @include('Student.partials.seo')
    @include('partials.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Installable as an app (optional - the website works the same without it). --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="QSU SG">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <script>
        // Before anything is drawn: opened from the home screen (the installed
        // app) rather than a browser tab? Then use the app layout - one section
        // per screen with tabs at the bottom - starting on the #section asked
        // for, or on Contact right after the contact form was sent.
        (function () {
            var app = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
            if (app) {
                var tab = @json(session()->has('success') || $errors->any()) ? 'contact' : location.hash.slice(1);
                document.documentElement.classList.add('is-app');
                document.documentElement.setAttribute('data-tab', ['announcements', 'documents', 'officers', 'about', 'contact'].indexOf(tab) > -1 ? tab : 'announcements');
            }

            // Light or dark: the visitor's own pick on this device (the sun/moon
            // button), else the device's setting. Kept apart from the officer
            // pages' choice (themeMode), so switching one doesn't switch the other.
            var device = window.matchMedia && matchMedia('(prefers-color-scheme: dark)');
            var saved = function () { try { return localStorage.getItem('sgTheme'); } catch (e) { return null; } };
            window.sgSetTheme = function (theme) {
                document.documentElement.setAttribute('data-theme', theme);
                document.querySelector('meta[name="color-scheme"]').setAttribute('content', theme);
                // The website's header is white or near-black; the app keeps its green bar.
                if (! app) document.querySelector('meta[name="theme-color"]').setAttribute('content', theme === 'dark' ? '#1b1b19' : '#ffffff');
                document.dispatchEvent(new Event('sg:theme'));
            };
            var pick = saved();
            sgSetTheme(pick === 'dark' || pick === 'light' ? pick : (device && device.matches ? 'dark' : 'light'));
            if (device && device.addEventListener) {
                device.addEventListener('change', function (e) {
                    var pick = saved();
                    if (pick !== 'dark' && pick !== 'light') sgSetTheme(e.matches ? 'dark' : 'light');
                });
            }
            // Chrome/Edge/Samsung offer to install on their own; hold that
            // offer for the page's "Get the app" button instead.
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                window.sgInstallPrompt = e;
                document.dispatchEvent(new Event('sg:installable'));
            });
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    @yield('content')

    <script>
        // The sun/moon button in each page's header (Student.partials.theme-toggle):
        // flip light/dark and remember it on this device.
        (function () {
            var root = document.documentElement;
            var reduceMotion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)');
            var buttons = document.querySelectorAll('[data-theme-toggle]');

            function sync() {
                var dark = root.getAttribute('data-theme') === 'dark';
                buttons.forEach(function (btn) {
                    btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
                    btn.querySelector('i').className = dark ? 'bi bi-sun' : 'bi bi-moon';
                });
            }

            // The theme being switched to. The fade below applies it a moment
            // later, so a quick second tap must not read the old one back.
            var target = root.getAttribute('data-theme');

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var next = target = target === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('sgTheme', next); } catch (e) { /* private mode: this visit only */ }
                    // One quick cross-fade of the whole screen, as on the officer
                    // pages; instant without View Transitions or with reduced motion.
                    if (document.startViewTransition && ! (reduceMotion && reduceMotion.matches)) {
                        document.startViewTransition(function () { sgSetTheme(next); });
                    } else {
                        sgSetTheme(next);
                    }
                });
            });

            document.addEventListener('sg:theme', function () {
                target = root.getAttribute('data-theme');
                sync();
            });
            sync();
        })();
    </script>
</body>
</html>
