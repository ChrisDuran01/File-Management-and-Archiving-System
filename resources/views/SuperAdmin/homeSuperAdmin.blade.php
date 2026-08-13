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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css' rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<style>
    @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap");

    :root{
        --header-height: 3rem;
        --nav-width: 58px;
        --nav-width-expanded: 224px;
        --first-color: #058028;
        --first-color-dark: #046322;
        --first-color-light: #AFA5D9;
        --white-color: #F7F6FB;
        --body-font: 'Nunito', sans-serif;
        --normal-font-size: 1rem;
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
        background-color: var(--white-color);
        z-index: var(--z-fixed);
        transition: .5s;
    }
    .header_toggle{ color: var(--first-color); font-size: 1.5rem; cursor: pointer; }
    .header_img{ width: 35px; height: 35px; display: flex; justify-content: center; border-radius: 50%; overflow: hidden; }
    .header_img img{ width: 40px; }

    /* ===== SIDEBAR ===== */
    .l-navbar{
        position: fixed;
        top: 0; left: -30%;
        width: var(--nav-width);
        height: 100vh;
        background: linear-gradient(180deg, var(--first-color) 0%, var(--first-color-dark) 100%);
        box-shadow: 2px 0 16px rgba(0,0,0,.1);
        padding: .5rem 0 0 0;
        transition: .5s;
        z-index: var(--z-fixed);
    }

    .nav{
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
    }

    /* Scrolls internally if the nav list (plus its section dividers) gets
       taller than the sidebar - so Profile/Sign Out at the bottom is never
       pushed off-screen or clipped, no matter how many links are above it. */
  
    .nav_logo{
        display: flex;
        align-items: center;
        column-gap: .75rem;
        padding: .5rem 0 1.15rem 1.4rem;
        margin-bottom: .6rem;
        border-bottom: 1px solid rgba(255,255,255,.14);
    }
    .nav_logo-icon{
        background: var(--white-color);
        width: 32px;
        height: 32px;
        border-radius: 9px;
        object-fit: contain;
        padding: 3px;
        flex-shrink: 0;
    }
    .nav_logo-name{
        color: var(--white-color);
        font-weight: 700;
        letter-spacing: .01em;
        white-space: nowrap;
    }

    .nav_list{
        display: flex;
        flex-direction: column;
        row-gap: .3rem;
        padding: 0 .6rem;
    }

    .nav_link{
        position: relative;
        display: grid;
        grid-template-columns: max-content max-content;
        align-items: center;
        column-gap: 1rem;
        padding: .65rem .8rem;
        border-radius: 10px;
        color: rgba(255,255,255,.68);
        transition: background .2s, color .2s;
        white-space: nowrap;
    }
    .nav_link:hover{ background: rgba(255,255,255,.1); color: var(--white-color); }
    .nav_icon{ font-size: 1.2rem; }

    /* Section divider - groups related nav items so the sidebar reads as
       Overview / Records / Administration instead of one flat list. */
    .nav_section{
        margin-top: .5rem;
        padding: .1rem 1rem .3rem;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(255,255,255,.4);
        border-top: 1px solid rgba(255,255,255,.12);
        white-space: nowrap;
        overflow: hidden;
    }
    .nav_list > .nav_section:first-child{ border-top: none; margin-top: 0; padding-top: 0; }

    .show{ left: 0; }
    .body-pd{ padding-left: calc(var(--nav-width) + 1rem); }

    /* Mirrors of .show/.body-pd, applied instantly (before JS runs) from the
       persisted sidebar state so the expanded/collapsed look doesn't flash
       or reset when navigating to a new page. */
    html.sidebar-expanded #nav-bar{ left: 0; }
    html.sidebar-expanded #header,
    html.sidebar-expanded #body-pd{ padding-left: calc(var(--nav-width) + 1rem); }

    .active{ background: rgba(255,255,255,.16); color: var(--white-color); }
    .active::before{
        content: '';
        position: absolute;
        left: -.6rem;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 60%;
        border-radius: 4px;
        background: var(--white-color);
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
        padding: .9rem .6rem .55rem;
        border-top: 1px solid rgba(255,255,255,.14);
        cursor: pointer;
        transition: background .2s;
        border-radius: 10px;
    }
    .user-profile:hover{ background: rgba(255,255,255,.08); }

    .user-profile img{
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255,255,255,.5);
        flex-shrink: 0;
    }

    .user-info{ display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
    .user-name{
        font-weight: 700;
        color: white;
        font-size: 13.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-role{
        font-size: 11px;
        color: rgba(255,255,255,.65);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-caret{
        margin-left: auto;
        color: rgba(255,255,255,.5);
        font-size: .9rem;
        flex-shrink: 0;
    }

    .user-dropdown{
        position: absolute;
        bottom: calc(100% + 6px);
        left: .6rem;
        width: 190px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 12px 32px rgba(0,0,0,.2);
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
        .show{ width: var(--nav-width-expanded); }
        .body-pd{ padding-left: calc(var(--nav-width) + 188px); }

        html.sidebar-expanded #nav-bar{ width: var(--nav-width-expanded); }
        html.sidebar-expanded #header,
        html.sidebar-expanded #body-pd{ padding-left: calc(var(--nav-width) + 188px); }
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
    </header>
    <div class="l-navbar" id="nav-bar">
        <nav class="nav">
            <div class="nav_top">
                <a href="#" class="nav_logo">
                    <img src="{{ asset('images/SG-logo.png') }}" alt="QSU-FMAS" class="nav_logo-icon"
                         onerror="this.onerror=null; this.src='https://placehold.co/64x64?text=SG';">
                    <span class="nav_logo-name">QSU-FMAS</span>
                </a>

                <div class="nav_list">
                    <div class="nav_section">Overview</div>

                    <a href="/superAdminDashboard" class="nav_link {{ Request::is('superAdminDashboard') ? 'active' : '' }}">
                        <i class='bx bx-grid-alt nav_icon'></i>
                        <span class="nav_name">Dashboard</span>
                    </a>

                    <div class="nav_section">Records</div>

                    <a href="/folders" class="nav_link {{ Request::is('folders') ? 'active' : '' }}">
                        <i class='bx bx-folder nav_icon'></i>
                        <span class="nav_name">Folders</span>
                    </a>

                    <a href="/archives" class="nav_link {{ Request::is('archives') ? 'active' : '' }}">
                        <i class='bx bx-archive nav_icon'></i>
                        <span class="nav_name">Archives</span>
                    </a>

                    <a href="/templates" class="nav_link {{ Request::is('templates') ? 'active' : '' }}">
                        <i class='bx bx-file-blank nav_icon'></i>
                        <span class="nav_name">Document Templates</span>
                    </a>

                    <a href="/reports" class="nav_link {{ Request::is('reports') ? 'active' : '' }}">
                        <i class='bx bx-chart nav_icon'></i>
                        <span class="nav_name">Reports &amp; Analytics</span>
                    </a>

                    <a href="/announcements" class="nav_link {{ Request::is('announcements') ? 'active' : '' }}">
                        <i class='bx bx-bullhorn nav_icon'></i>
                        <span class="nav_name">Announcements</span>
                    </a>

                    <a href="/site-settings" class="nav_link {{ Request::is('site-settings') ? 'active' : '' }}">
                        <i class='bx bx-file-blank nav_icon'></i>
                        <span class="nav_name">Site Content</span>
                    </a>

                    <div class="nav_section">Administration</div>

                    <a href="/manageAdmins" class="nav_link {{ Request::is('manageAdmins') ? 'active' : '' }}">
                        <i class='bi bi-people nav_icon'></i>
                        <span class="nav_name">Manage Admins</span>
                    </a>

                    <a href="/activityLogs" class="nav_link {{ Request::is('activityLogs') ? 'active' : '' }}">
                        <i class='bi bi-activity nav_icon'></i>
                        <span class="nav_name">Activity Logs</span>
                    </a>

                    <a href="/backup" class="nav_link {{ Request::is('backup') ? 'active' : '' }}">
                        <i class='bi bi-gear nav_icon'></i>
                        <span class="nav_name">Settings</span>
                    </a>

                    
                </div>
            </div>

            <div class="user-menu">

                <div class="user-dropdown" id="userDropdown">

                    <a href="/profile">
                        <i class='bx bx-user'></i>
                        Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
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

                <div class="user-profile" id="profileToggle">

                    <img src="{{ Auth::user()->profile_photo
                            ? asset('storage/'.Auth::user()->profile_photo)
                            : 'https://ui-avatars.com/api/?name='.urlencode(Auth::user()->name) }}"
                         alt="profile">

                    <div class="user-info">
                        <div class="user-name">{{ Auth::user()->name }}</div>
                        <div class="user-role">Super Administrator</div>
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
    const profileToggle = document.getElementById("profileToggle");
    const userDropdown = document.getElementById("userDropdown");

    profileToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function () {
        userDropdown.classList.remove("show");
    });
</script>
</body>
</html>
