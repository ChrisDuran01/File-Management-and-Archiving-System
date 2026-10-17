{{--
    Frame for every screen of the officer phone app (/app).

    @section('bar')      the top bar (omit for a screen without one, e.g. sign-in)
    @section('content')  the screen
    @section('footer')   an action bar pinned to the bottom, INSTEAD of the tabs
    $tab                 which bottom tab is current: home | folders | search | offline
                         (null on a drill-in screen that has its own footer)

    Styles and behaviour live in public/officer-app/app.css and app.js; the
    values app.js needs from the server are handed over in window.SGO below.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f6f5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QSU SG Officers')</title>

    <link rel="manifest" href="{{ asset('officer.webmanifest') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/officer-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/officer-apple-touch.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SG Officers">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('officer-app/app.css') }}?v={{ filemtime(public_path('officer-app/app.css')) }}">
</head>
@php
    $hasFooter = View::hasSection('footer');
    $hasTabs   = ! $hasFooter && Auth::check() && ($tabs ?? true);
@endphp
<body class="{{ $hasFooter ? 'has-footer' : '' }} {{ $hasTabs ? 'has-tabs' : '' }}">
<div class="app">
    @yield('bar')

    <main class="app-main {{ $mainClass ?? '' }}">
        @if (session('success'))
            <div class="notice green" role="status"><div class="notice-text">{{ session('success') }}</div></div>
        @endif
        @if (session('error'))
            <div class="notice red" role="alert"><div class="notice-text">{{ session('error') }}</div></div>
        @endif

        @yield('content')
    </main>

    @if ($hasFooter)
        <footer class="app-footer">@yield('footer')</footer>
    @elseif ($hasTabs)
        @include('OfficerApp.partials.tabs', ['tab' => $tab ?? null])
    @endif
</div>

@php
    $sgo = [
        'user'       => Auth::id(),
        'csrf'       => csrf_token(),
        'expireDays' => config('officer_app.offline.expire_days'),
        'urls'       => [
            'sw'      => asset('officer-sw.js'),
            'scope'   => url('app'),
            'offline' => route('app.offline'),
            'sync'    => route('app.sync'),
            'saved'   => route('app.sync.saved'),
            'read'    => route('notifications.read'),
        ],
        'libs'       => [
            'pdf'       => asset('vendor/officer-app/pdf.min.js'),
            'pdfWorker' => asset('vendor/officer-app/pdf.worker.min.js'),
            'jszip'     => asset('vendor/officer-app/jszip.min.js'),
            'docx'      => asset('vendor/officer-app/docx-preview.min.js'),
        ],
    ];
@endphp
<script>window.SGO = @json($sgo);</script>
<script src="{{ asset('officer-app/app.js') }}?v={{ filemtime(public_path('officer-app/app.js')) }}" defer></script>
</body>
</html>
