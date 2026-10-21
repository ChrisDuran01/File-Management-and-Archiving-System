<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f3d1f">
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
            } else {
                // The website's header is white; only the app keeps the green bar.
                document.querySelector('meta[name="theme-color"]').setAttribute('content', '#ffffff');
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
</body>
</html>
