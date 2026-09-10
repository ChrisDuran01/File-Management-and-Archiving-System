<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function () {
            try {
                if (localStorage.getItem('sidebarExpanded') === '1') {
                    document.documentElement.classList.add('sidebar-expanded');
                }
            } catch (e) {}
        })();
    </script>
    @include('partials.theme-mode')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>QSU-FMAS</title>
    {{-- Bootstrap CSS version MUST match the 5.3.0 JS bundle the pages load:
         the old 5.0.0-beta1 CSS made dropdown-menu-end menus open off-screen,
         because 5.3's JS positions static dropdowns via a [data-bs-popper]
         attribute that beta1's CSS resolves with the wrong alignment. --}}
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css' rel="stylesheet">

</head>
<style>
    @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");

    :root{
        --header-height: 3rem;
        --nav-width: 58px;
        --nav-width-expanded: 224px;
        --first-color: #058028;          /* brand green - used only as accent */
        --first-color-dark: #046322;
        --accent-dim: #e9f5ee;           /* pale green tint for the active item */
        --sidebar-bg: #ffffff;
        --sidebar-border: #e8eaf0;
        --nav-text: #64748b;             /* resting link color */
        --nav-text-strong: #0f172a;      /* hover / active text */
        --nav-hover-bg: #f4f6f9;
        --white-color: #F7F6FB;
        --body-font: 'Inter', sans-serif;
        --normal-font-size: .95rem;
        --z-fixed: 100;
    }

    *, ::before, ::after { box-sizing: border-box; }

    body{
        position: relative;
        margin: var(--header-height) 0 0 0;
        padding: 0 1rem;
        font-family: var(--body-font);
        font-size: var(--normal-font-size);
        transition: .5s;
        transform: none !important;
    }

    a{ text-decoration: none; }

    /* ===== HEADER ===== */
    .header{
        width: 100%;
        height: var(--header-height);
        position: fixed;
        top: 0; left: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1rem;
        background-color: #fff;
        border-bottom: 1px solid var(--sidebar-border);
        z-index: var(--z-fixed);
        transition: .5s;
    }
    .header_toggle{ color: var(--nav-text); font-size: 1.35rem; cursor: pointer; transition: color .2s; }
    .header_toggle:hover{ color: var(--nav-text-strong); }
    .header_actions{ display: flex; align-items: center; gap: 2px; }
    .theme-toggle-btn{ font-size: 1.2rem; }
    .header_img{ width: 35px; height: 35px; display: flex; justify-content: center; border-radius: 50%; overflow: hidden; }
    .header_img img{ width: 40px; }

    /* ===== NOTIFICATION BELL (header, right side) ===== */
    .notif-bell{ position: relative; }
    .notif-btn{
        background: none; border: none; cursor: pointer; display: flex;
        color: var(--nav-text); font-size: 1.25rem; padding: 6px;
        border-radius: 8px; position: relative;
        transition: background .15s, color .15s;
    }
    .notif-btn:hover{ background: var(--nav-hover-bg); color: var(--nav-text-strong); }
    .notif-badge{
        position: absolute; top: 0; right: 0;
        min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px;
        background: #dc2626; color: #fff; font-size: 10px; font-weight: 700;
        display: none; align-items: center; justify-content: center; line-height: 1;
    }
    /* State classes deliberately NOT named "show": the sidebar's global
       .show{left:0;width:...} rules would hijack any element carrying it. */
    .notif-badge.on{ display: flex; }
    .notif-panel{
        position: absolute; top: calc(100% + 10px); right: 0; width: 330px;
        background: #fff; border: 1px solid var(--sidebar-border); border-radius: 12px;
        box-shadow: 0 12px 32px rgba(15,23,42,.12); display: none; z-index: 1100; overflow: hidden;
    }
    .notif-panel.open{ display: block; }
    .notif-head{
        display: flex; justify-content: space-between; align-items: center;
        padding: 11px 14px; border-bottom: 1px solid var(--sidebar-border);
        font-weight: 600; font-size: .85rem; color: var(--nav-text-strong);
    }
    .notif-head button{ background: none; border: none; color: var(--first-color); font-size: .75rem; font-weight: 600; cursor: pointer; }
    .notif-list{ max-height: 360px; overflow-y: auto; }
    .notif-item{
        display: flex; gap: 10px; padding: 11px 14px;
        border-bottom: 1px solid #f2f4f8; cursor: pointer; transition: background .12s;
    }
    .notif-item:hover{ background: var(--nav-hover-bg); }
    .notif-item:last-child{ border-bottom: none; }
    .notif-item.unread{ background: #e9f5ee; }
    .notif-ico{
        width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 15px;
    }
    .notif-body{ min-width: 0; }
    .notif-title{ font-size: .8rem; font-weight: 600; color: var(--nav-text-strong); }
    .notif-msg{
        font-size: .75rem; color: var(--nav-text); overflow: hidden;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    }
    .notif-when{ font-size: .68rem; color: #9aa3b2; margin-top: 2px; }
    .notif-empty{ padding: 30px 14px; text-align: center; color: #9aa3b2; font-size: .8rem; }

    /* ===== SIDEBAR (minimalist: white, hairline border, one green accent) ===== */
    .l-navbar{
        position: fixed;
        top: 0; left: -30%;
        width: var(--nav-width);
        height: 100vh;
        background: var(--sidebar-bg);
        border-right: 1px solid var(--sidebar-border);
        padding: .5rem 0 0 0;
        transition: .5s;
        z-index: var(--z-fixed);
    }

    .nav{
        height: 100%;
        display: flex;
        flex-direction: column;
        /* Bootstrap also styles `.nav` and sets flex-wrap: wrap - without
           this override, the user menu wraps into an invisible second
           column beside the (full-height) nav list instead of sitting
           below it. */
        flex-wrap: nowrap;
        justify-content: space-between;
        overflow: hidden;
    }

    /* Let a long menu scroll instead of clipping on short screens */
    /* min-height:0 lets this flex child actually shrink and scroll - without
       it, a long menu pushes the user profile below the viewport. */
    .nav > div:first-child{ min-height: 0; overflow-y: auto; overflow-x: hidden; scrollbar-width: thin; scrollbar-color: #d8dde5 transparent; }
    .nav > div:first-child::-webkit-scrollbar{ width: 4px; }
    .nav > div:first-child::-webkit-scrollbar-thumb{ background: #d8dde5; border-radius: 4px; }

    .nav_logo{
        display: flex;
        align-items: center;
        column-gap: .75rem;
        padding: .5rem 0 1rem 1.05rem;
        margin-bottom: .5rem;
        border-bottom: 1px solid var(--sidebar-border);
    }
    .nav_logo-icon{
        background: #fff;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: 1px solid var(--sidebar-border);
        object-fit: contain;
        padding: 3px;
        flex-shrink: 0;
    }
    .nav_logo-name{
        color: var(--nav-text-strong);
        font-weight: 700;
        font-size: .92rem;
        letter-spacing: -.01em;
        white-space: nowrap;
    }

    .nav_list{
        display: flex;
        flex-direction: column;
        row-gap: 2px;
        padding: 0 .6rem;
    }

    .nav_link{
        position: relative;
        display: grid;
        grid-template-columns: max-content max-content;
        align-items: center;
        column-gap: .9rem;
        padding: .55rem .72rem;
        border-radius: 8px;
        color: var(--nav-text);
        font-size: .86rem;
        font-weight: 500;
        transition: background .15s, color .15s;
        white-space: nowrap;
    }
    .nav_link:hover{ background: var(--nav-hover-bg); color: var(--nav-text-strong); }
    .nav_icon{ font-size: 1.1rem; }

    /* Scoped to the sidebar element: Bootstrap toggles a bare `show` class
       on every dropdown, modal and alert it opens, so a global `.show`
       rule here would hijack all of them (it used to force left:0 and a
       224px width onto the file three-dot menus and the notification
       panel). */
    #nav-bar.show{ left: 0; }
    .body-pd{ padding-left: calc(var(--nav-width) + 1rem); }

    /* Mirrors of .show/.body-pd, applied instantly (before JS runs) from the
       persisted sidebar state so the expanded/collapsed look doesn't flash
       or reset when navigating to a new page. */
    html.sidebar-expanded #nav-bar{ left: 0; }
    html.sidebar-expanded #header,
    html.sidebar-expanded #body-pd{ padding-left: calc(var(--nav-width) + 1rem); }

    /* Collapsed sidebar is icons-only: every text element is hidden and
       fades in slightly after the width animation once the sidebar expands
       (see the expanded rules inside the media query below). */
    .nav_name, .nav_logo-name, .user-info, .user-caret{
        opacity: 0;
        visibility: hidden;
        transition: opacity .2s ease .12s;
    }

    /* Scoped to nav links so Bootstrap's own .active (tabs, pagination)
       inside page content is never restyled by the layout. */
    .nav_link.active{ background: var(--accent-dim); color: var(--first-color-dark); font-weight: 600; }
    .nav_link.active .nav_icon{ color: var(--first-color); }
    .nav_link.active::before{
        content: '';
        position: absolute;
        left: -.6rem;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 55%;
        border-radius: 4px;
        background: var(--first-color);
    }

    .height-100{ height: 100vh; }

    /* ===== USER MENU (bottom of sidebar) ===== */
    .user-menu{
        position: relative;
        padding: 0 .6rem .9rem;
        flex-shrink: 0;
    }

    .user-profile{
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .8rem .55rem .55rem;
        border-top: 1px solid var(--sidebar-border);
        cursor: pointer;
        transition: background .15s;
        border-radius: 8px;
    }
    .user-profile:hover{ background: var(--nav-hover-bg); }

    .user-profile img{
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid var(--sidebar-border);
        flex-shrink: 0;
    }

    .user-info{ display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
    .user-name{
        font-weight: 600;
        color: var(--nav-text-strong);
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-role{
        font-size: 11px;
        color: var(--nav-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-caret{
        margin-left: auto;
        color: #b6bdc9;
        font-size: .9rem;
        flex-shrink: 0;
    }

    .user-dropdown{
        position: absolute;
        bottom: calc(100% + 6px);
        left: .6rem;
        width: 190px;
        background: white;
        border: 1px solid var(--sidebar-border);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15,23,42,.08);
        overflow: hidden;
        display: none;
        z-index: 999;
    }
    .user-dropdown.show{ display: block; }
    .user-dropdown a{
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 16px;
        color: #333;
        font-size: 14px;
        transition: background .15s;
    }
    .user-dropdown a:hover{ background: #f2f7f4; color: var(--first-color); }
    .user-dropdown a.signout{ color: #b3261e; border-top: 1px solid #eee; }
    .user-dropdown a.signout:hover{ background: #fdecea; color: #b3261e; }
    .user-dropdown i{ font-size: 16px; width: 16px; text-align: center; }

    @media screen and (min-width: 768px){
        body{ margin: calc(var(--header-height) + 1rem) 0 0 0; padding-left: calc(var(--nav-width) + 2rem); }
        .header{ height: calc(var(--header-height) + 1rem); padding: 0 2rem 0 calc(var(--nav-width) + 2rem); }
        .header_img{ width: 40px; height: 40px; }
        .header_img img{ width: 45px; }
        .l-navbar{ left: 0; padding: 1rem 0 0 0; }
        #nav-bar.show{ width: var(--nav-width-expanded); }
        .body-pd{ padding-left: calc(var(--nav-width) + 188px); }

        html.sidebar-expanded #nav-bar{ width: var(--nav-width-expanded); }
        html.sidebar-expanded #header,
        html.sidebar-expanded #body-pd{ padding-left: calc(var(--nav-width) + 188px); }

        /* Expanded sidebar: bring the text back (desktop only - the mobile
           slide-in stays at icon width, so its labels stay hidden). */
        #nav-bar.show .nav_name, html.sidebar-expanded #nav-bar .nav_name,
        #nav-bar.show .nav_logo-name, html.sidebar-expanded #nav-bar .nav_logo-name,
        #nav-bar.show .user-info, html.sidebar-expanded #nav-bar .user-info,
        #nav-bar.show .user-caret, html.sidebar-expanded #nav-bar .user-caret{
            opacity: 1;
            visibility: visible;
        }
    }

    /* Force modals to cover the real viewport (vw/vh instead of %, so it
       can't be shrunk by whatever containing block is confusing position:fixed
       on this page). Dialog is centered via absolute + transform instead of
       display:flex, since flex-centering depended on the .show class — and
       Bootstrap removes .show at the START of the closing fade, which made
       the dialog snap out of place before the fade-out finished. Absolute+
       transform centering doesn't depend on .show, so it stays put through
       the whole open/close animation. */
    .modal{
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .modal-dialog{
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-backdrop{
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
    }
</style>
<body id="body-pd">
    <header class="header" id="header">
        <div class="header_toggle"> <i class='bx bx-menu' id="header-toggle"></i> </div>

        <div class="header_actions">
            @include('partials.theme-toggle')

            <div class="notif-bell">
                <button type="button" class="notif-btn" id="notifBtn" aria-label="Notifications">
                    <i class='bx bx-bell'></i>
                    <span class="notif-badge" id="notifBadge"></span>
                </button>
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-head">
                        <span>Notifications</span>
                        <button type="button" id="notifMarkAll">Mark all read</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-empty">No notifications yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="l-navbar" id="nav-bar">
        <nav class="nav">
            <div>
                <a href="#" class="nav_logo">
                    <img src="{{ asset('images/SG-logo.png') }}" alt="QSU-FMAS" class="nav_logo-icon"
                         onerror="this.onerror=null; this.src='https://placehold.co/64x64?text=SG';">
                    <span class="nav_logo-name">QSU-FMAS</span>
                </a>

                <div class="nav_list">
                    <a href="/adminDashboard" class="nav_link {{ Request::is('adminDashboard') ? 'active' : '' }}">
                        <i class='bx bx-grid-alt nav_icon'></i>
                        <span class="nav_name">Dashboard</span>
                    </a>

                    <a href="/folders" class="nav_link {{ Request::is('folders') ? 'active' : '' }}">
                        <i class='bx bx-folder nav_icon'></i>
                        <span class="nav_name">Folders</span>
                    </a>

                    <a href="/documents" class="nav_link {{ Request::is('documents') || (Request::is('documents/*') && ! Request::is('documents/scans*')) ? 'active' : '' }}">
                        <i class='bx bx-file nav_icon'></i>
                        <span class="nav_name">Documents</span>
                    </a>

                    <a href="/documents/scans" class="nav_link {{ Request::is('documents/scans*') ? 'active' : '' }}">
                        <i class='bx bx-import nav_icon'></i>
                        <span class="nav_name">Scan Inbox</span>
                    </a>

                    <a href="/archives" class="nav_link {{ Request::is('archives') ? 'active' : '' }}">
                        <i class='bx bx-archive nav_icon'></i>
                        <span class="nav_name">Archives</span>
                    </a>

                    <a href="/trash" class="nav_link {{ Request::is('trash') ? 'active' : '' }}">
                        <i class='bx bx-trash nav_icon'></i>
                        <span class="nav_name">Trash</span>
                    </a>

                    <a href="/templates" class="nav_link {{ Request::is('templates') ? 'active' : '' }}">
                        <i class='bx bx-file-blank nav_icon'></i>
                        <span class="nav_name">Document Templates</span>
                    </a>

                    <a href="/reports" class="nav_link {{ Request::is('reports') ? 'active' : '' }}">
                        <i class='bx bx-chart nav_icon'></i>
                        <span class="nav_name">Reports & Analytics</span>
                    </a>

                    <a href="/announcements" class="nav_link {{ Request::is('announcements') ? 'active' : '' }}">
                        <i class='bx bx-bullhorn nav_icon'></i>
                        <span class="nav_name">Announcements</span>
                    </a>

                    <a href="/site-settings" class="nav_link {{ Request::is('site-settings') ? 'active' : '' }}">
                        <i class='bx bx-file-blank nav_icon'></i>
                        <span class="nav_name">Site Content</span>
                    </a>

                    <a href="/profile" class="nav_link {{ Request::is('profile') ? 'active' : '' }}">
                        <i class='bx bx-user nav_icon'></i>
                        <span class="nav_name">Profile</span>
                    </a>
                </div>
            </div>

            <div class="user-menu">

                <div class="user-dropdown" id="userDropdown">

                    <a href="/profile">
                        <i class='bx bx-user'></i>
                        Profile
                    </a>

                    <form method="POST"
                          action="{{ route('logout') }}"
                          id="logout-form">

                        @csrf

                        <a href="#"
                           class="signout"
                           onclick="event.preventDefault();
                                    document.getElementById('logout-form').submit();">

                            <i class='bx bx-log-out'></i>
                            Sign Out

                        </a>

                    </form>

                </div>

                <div class="user-profile"
                     id="profileToggle">

                    <img src="{{ Auth::user()->profile_photo
                            ? asset('storage/'.Auth::user()->profile_photo)
                            : 'https://ui-avatars.com/api/?name='.urlencode(Auth::user()->name) }}"
                         alt="profile">

                    <div class="user-info">

                        <div class="user-name">
                            {{ Auth::user()->name }}
                        </div>

                        <div class="user-role">
                            {{ Auth::user()->activeOfficerTerm?->position?->position_name ?? 'Administrator' }}
                        </div>

                    </div>

                    <i class='bx bx-chevron-up user-caret'></i>

                </div>

            </div>
        </nav>
    </div>

    @yield('content')

<script>document.addEventListener("DOMContentLoaded", function(event) {

const showNavbar = (toggleId, navId, bodyId, headerId) =>{
const toggle = document.getElementById(toggleId),
nav = document.getElementById(navId),
bodypd = document.getElementById(bodyId),
headerpd = document.getElementById(headerId)

// Validate that all variables exist
if(toggle && nav && bodypd && headerpd){

// Sync the real element classes to whatever was persisted, so the very
// next click toggles from the correct baseline (CSS already painted this
// state instantly via the sidebar-expanded class on <html>).
let expanded = false;
try { expanded = localStorage.getItem('sidebarExpanded') === '1' } catch(e) {}
if (expanded) {
    nav.classList.add('show')
    toggle.classList.add('bx-x')
    bodypd.classList.add('body-pd')
    headerpd.classList.add('body-pd')
}

toggle.addEventListener('click', ()=>{
// show navbar
nav.classList.toggle('show')
// change icon
toggle.classList.toggle('bx-x')
// add padding to body
bodypd.classList.toggle('body-pd')
// add padding to header
headerpd.classList.toggle('body-pd')

// Persist so the expanded/collapsed state survives navigating to a new page
const isExpanded = nav.classList.contains('show')
document.documentElement.classList.toggle('sidebar-expanded', isExpanded)
try { localStorage.setItem('sidebarExpanded', isExpanded ? '1' : '0') } catch(e) {}
})
}
}

showNavbar('header-toggle','nav-bar','body-pd','header')

/*===== LINK ACTIVE =====*/
const linkColor = document.querySelectorAll('.nav_link')

function colorLink(){
if(linkColor){
linkColor.forEach(l=> l.classList.remove('active'))
this.classList.add('active')
}
}
linkColor.forEach(l=> l.addEventListener('click', colorLink))

 // Your code to run since DOM is loaded and ready
});</script>

<script>
    const profileToggle=document.getElementById("profileToggle");

const userDropdown=document.getElementById("userDropdown");

profileToggle.addEventListener("click",function(e){

    e.stopPropagation();

    userDropdown.classList.toggle("show");

});

document.addEventListener("click",function(){

    userDropdown.classList.remove("show");

});
</script>

<script>
// Notification bell: polls the JSON endpoint for the unread badge + recent
// list (same pattern as the backup progress poller), marks items read on
// click, then follows their link.
(function () {
    const btn = document.getElementById('notifBtn'),
          panel = document.getElementById('notifPanel'),
          list = document.getElementById('notifList'),
          badge = document.getElementById('notifBadge'),
          markAll = document.getElementById('notifMarkAll');
    if (!btn || !panel || !list || !badge) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    function render(data) {
        badge.textContent = data.unread > 99 ? '99+' : data.unread;
        badge.classList.toggle('on', data.unread > 0);

        if (!data.notifications.length) {
            list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            return;
        }

        list.innerHTML = data.notifications.map(n =>
            '<div class="notif-item' + (n.read ? '' : ' unread') + '" data-id="' + esc(n.id) + '" data-link="' + esc(n.link) + '">' +
                '<div class="notif-ico" style="background:' + esc(n.color) + '1a;color:' + esc(n.color) + ';"><i class="' + esc(n.icon) + '"></i></div>' +
                '<div class="notif-body">' +
                    '<div class="notif-title">' + esc(n.title) + '</div>' +
                    '<div class="notif-msg">' + esc(n.message) + '</div>' +
                    '<div class="notif-when">' + esc(n.when) + '</div>' +
                '</div>' +
            '</div>'
        ).join('');
    }

    function poll() {
        fetch("{{ route('notifications.index') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(render).catch(() => {});
    }

    list.addEventListener('click', function (e) {
        const item = e.target.closest('.notif-item');
        if (!item) return;
        fetch("{{ route('notifications.read') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ id: item.dataset.id }),
        }).finally(() => {
            if (item.dataset.link && item.dataset.link !== '#') window.location = item.dataset.link;
        });
    });

    markAll.addEventListener('click', function () {
        fetch("{{ route('notifications.read') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: '{}',
        }).then(poll);
    });

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) poll();
    });

    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target)) panel.classList.remove('open');
    });

    poll();
    setInterval(poll, 30000);
})();
</script>

@include('partials.help-bot')
@include('partials.idle-timeout')
</body>
</html>
