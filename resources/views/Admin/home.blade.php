<!DOCTYPE html>
<html lang="en">
<head>
    {{-- Paint the remembered menu width before anything renders, so the
         sidebar doesn't jump on every page load (partials/app-shell/scripts). --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('sidebarCollapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
    @include('partials.theme-mode')
    {{-- What scrolls the page: the page panel on laptops (it stays in place
         like the sidebar, partials/app-shell/styles), the window on phones.
         Page scripts use these instead of window.scrollY / window.scrollTo /
         the window's scroll event, so they work either way. --}}
    <script>
        window.appScroll = {
            panel: function () {
                var main = document.getElementById('main-content');
                return main && getComputedStyle(main).overflowY !== 'visible' ? main : null;
            },
            y: function () {
                var panel = this.panel();
                return panel ? panel.scrollTop : window.scrollY;
            },
            on: function (fn) {
                window.addEventListener('scroll', fn, { passive: true });
                var main = document.getElementById('main-content');
                if (main) main.addEventListener('scroll', fn, { passive: true });
            },
            toTop: function () {
                (this.panel() || window).scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Each page names itself with @section('title', 'Trash'), so its tab
         reads "Trash · QSU-FMAS" and open tabs can be told apart. --}}
    <title>
        @hasSection('title')
            @yield('title') · QSU-FMAS
        @else
            QSU-FMAS
        @endif
    </title>
    @include('partials.favicon')
    {{-- Bootstrap CSS version MUST match the 5.3.0 JS bundle the pages load:
         the old 5.0.0-beta1 CSS made dropdown-menu-end menus open off-screen,
         because 5.3's JS positions static dropdowns via a [data-bs-popper]
         attribute that beta1's CSS resolves with the wrong alignment. --}}
    <link href='{{ asset('vendor/bootstrap/bootstrap.min.css') }}' rel='stylesheet'>
    {{-- Boxicons: the search box, bell, theme switch, help chat and account menu use it. --}}
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.css' rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.app-shell.styles')
</head>
{{-- The one layout for every officer page; SuperAdmin/homeSuperAdmin extends
     it, and the sidebar shows Administration to the Super Admin only. --}}
<body id="body-pd" class="shell">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    @include('partials.app-shell.sidebar')

    <div class="app-panel">
        <header class="header app-header" id="header">
            <button type="button" class="header_toggle" id="header-toggle"
                    aria-label="Open menu" aria-expanded="false" aria-controls="nav-bar">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="header_actions">
                @include('partials.header-search')
                @include('partials.theme-toggle')

                @include('partials.notification-bell')
            </div>
        </header>

        <main id="main-content" tabindex="-1">
        @yield('content')
        </main>
    </div>

@include('partials.app-shell.shortcuts')
@include('partials.app-shell.scripts')
@include('partials.toasts')
@include('partials.confirm-dialog')
@include('partials.help-bot')
@include('partials.idle-timeout')
</body>
</html>
