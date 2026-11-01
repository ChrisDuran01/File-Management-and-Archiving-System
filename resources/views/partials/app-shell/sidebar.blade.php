{{--
    The officer sidebar, shared by both layouts (Admin/home, which
    SuperAdmin/homeSuperAdmin extends): a floating panel with the pages
    grouped by what officers do. Administration shows only to the Super
    Admin. Styles in partials/app-shell/styles, behaviour in
    partials/app-shell/scripts. Chosen from five mockups on 2026-10-25
    ("Floating panels").
--}}
@php
    $shellUser = Auth::user();
    $shellSuperAdmin = \App\Models\Folder::isSuperAdmin($shellUser);
    $shellLogo = rescue(fn () => \App\Models\SiteSetting::current()->logo_path, null, false);
    $shellDashboard = \App\Support\OfficerMenu::dashboard($shellUser);
    // The pages, their groups and their "G then a letter" keys (App\Support\OfficerMenu).
    $shellGroups = \App\Support\OfficerMenu::groups($shellUser);

    // Thin line icons, one set for the whole menu (24x24, drawn with the text colour).
    $shellIcons = [
        'home'      => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'chart'     => 'M3 3v18h18M7 15l4-4 3 3 5-6',
        'folder'    => 'M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
        'file'      => 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6',
        'inbox'     => 'M22 12h-6l-2 3h-4l-2-3H2M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z',
        'archive'   => 'M3 4h18v4H3zM5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4',
        'trash'     => 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14',
        'template'  => 'M4 4h16v16H4zM4 9h16M9 9v11',
        'clipboard' => 'M9 3h6v4H9zM8 5H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2M9 12h6M9 16h4',
        'megaphone' => 'M3 11v2a1 1 0 0 0 1 1h3l6 4V6L7 10H4a1 1 0 0 0-1 1zM17 9a4 4 0 0 1 0 6',
        'globe'     => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18',
        'users'     => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 7.5M22 21a6 6 0 0 0-4-5.6',
        'activity'  => 'M3 12h4l3-8 4 16 3-8h4',
        'gear'      => 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1',
        'collapse'  => 'M11 17l-5-5 5-5M18 17l-5-5 5-5',
    ];
    $shellIcon = fn (string $name) => '<svg class="sb-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="'.$shellIcons[$name].'"/></svg>';
@endphp

<div class="app-sidebar" id="nav-bar">
    {{-- alt="" because the name is written right beside it --}}
    <a href="{{ $shellDashboard }}" class="sb-brand" title="QSU-FMAS">
        <img src="{{ $shellLogo ? asset('storage/'.$shellLogo) : asset('images/SG-logo.png') }}" alt="" class="sb-logo"
             onerror="this.onerror=null; this.src='{{ asset('images/SG-logo.png') }}';">
        <span class="sb-brand-text">
            <b>QSU-FMAS</b>
            <small>SY {{ \App\Support\SchoolYear::current() }}</small>
        </span>
    </a>

    <nav class="sb-scroll" aria-label="Main menu">
        @foreach ($shellGroups as $label => $items)
            <div class="sb-group">
                <p class="sb-label">{{ $label }}</p>
                @foreach ($items as $item)
                    <a href="{{ $item['href'] }}" class="sb-link{{ $item['active'] ? ' active' : '' }}"
                       title="{{ $item['name'] }} (G then {{ strtoupper($item['key']) }})" data-go-key="{{ $item['key'] }}"@if ($item['active']) aria-current="page"@endif>
                        {!! $shellIcon($item['icon']) !!}
                        <span class="sb-name">{{ $item['name'] }}</span>
                        @if ($item['count'] > 0)
                            <span class="sb-count">{{ $item['count'] }}<span class="visually-hidden"> waiting for review</span></span>
                        @endif
                        {{-- Shown for a moment after G is pressed (partials/app-shell/scripts). --}}
                        <kbd class="kb-key sb-key" aria-hidden="true">{{ strtoupper($item['key']) }}</kbd>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="sb-foot user-menu">
        {{-- The menu comes after its button so Tab reaches it next;
             CSS still opens it above the button. --}}
        <button type="button" class="user-profile" id="profileToggle"
                aria-haspopup="true" aria-expanded="false" aria-controls="userDropdown">
            {{-- alt="" because the name is written right beside it --}}
            <img src="{{ $shellUser->profile_photo
                    ? asset('storage/'.$shellUser->profile_photo)
                    : 'https://ui-avatars.com/api/?name='.urlencode($shellUser->name) }}"
                 alt="">
            <span class="user-info">
                <span class="user-name">{{ $shellUser->name }}</span>
                <span class="user-role">{{ $shellSuperAdmin ? 'Super Administrator' : ($shellUser->activeOfficerTerm?->position?->position_name ?? 'Administrator') }}</span>
            </span>
            <i class='bx bx-chevron-up user-caret' aria-hidden="true"></i>
        </button>

        <button type="button" class="sb-collapse" id="sidebar-collapse"
                aria-label="Collapse menu" aria-expanded="true" aria-controls="nav-bar" aria-keyshortcuts="["
                title="Collapse menu ( [ )">
            {!! $shellIcon('collapse') !!}
        </button>

        <div class="user-dropdown" id="userDropdown">
            <a href="/profile">
                <i class='bx bx-user' aria-hidden="true"></i>
                Profile
            </a>
            <button type="button" class="ud-item" id="openShortcuts" aria-keyshortcuts="?">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18v12H3zM7 10h.01M11 10h.01M15 10h.01M7 14h10"/></svg>
                Keyboard shortcuts
                <kbd class="kb-key">?</kbd>
            </button>
            <form method="POST" action="{{ route('logout') }}" id="logout-form">
                @csrf
                <a href="#"
                   class="signout"
                   role="button"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class='bx bx-log-out' aria-hidden="true"></i>
                    Sign Out
                </a>
            </form>
        </div>
    </div>
</div>

{{-- Phones: the dark layer behind the open menu; tapping it closes the menu. --}}
<div class="sb-backdrop" id="sidebar-backdrop" hidden></div>
