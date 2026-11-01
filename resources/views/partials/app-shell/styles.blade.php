{{--
    Layout of every officer page: the sidebar and the page sit as two
    floating panels on a soft green-gray background ("Floating panels",
    2026-10-25). The window itself scrolls (Activity Logs' back-to-top and
    the help chat listen to it), so the page panel grows with its content
    and only its top bar stays in view. Dark mode keeps the colours it had
    before the redesign (Admin/partials/theme), only arranged as panels.
--}}
<style>
    :root {
        --header-height: 3.5rem;        /* the page panel's top bar; partials/toasts sits below it */
        --shell-gap: 10px;              /* space around and between the panels */
        --sb-width: 248px;
        --sb-width-collapsed: 64px;

        --canvas: #e7ece9;              /* the background the two panels float on */
        --sb-bg: #ffffff;
        --sb-border: var(--border, #e8eaf0);
        --sb-ink: var(--text-1, #111827);
        --sb-ink-2: var(--text-2, #4b5563);
        --sb-muted: var(--text-3, #646b78);
        --sb-hover: #f1f4f2;
        --sb-active-bg: var(--primary-fill, #058028);   /* white text on it */
        --sb-active-ink: #ffffff;
        --panel-shadow: 0 1px 2px rgba(16, 40, 28, .05);

        /* Older names that pages and partials still use (header search,
           share sheet, confirm dialog, dark-mode hovers). Dark values come
           from Admin/partials/theme. */
        --first-color: #058028;
        --first-color-dark: #046322;
        --accent-dim: #e9f5ee;
        --sidebar-bg: #ffffff;
        --sidebar-border: #e8eaf0;
        --nav-text: #64748b;
        --nav-text-strong: #0f172a;
        --nav-hover-bg: #f4f6f9;
        --white-color: #F7F6FB;
        --body-font: 'Inter', sans-serif;
        --normal-font-size: .95rem;
        --z-fixed: 100;
    }
    :root[data-theme="dark"] {
        /* Today's dark mode, unchanged: near-black page, #111110 menu,
           light green text on dark green for the current page. */
        --canvas: #0a0a0a;
        --sb-bg: #111110;
        --sb-border: #262523;
        --sb-ink: #ededec;
        --sb-ink-2: #a1a09a;
        --sb-muted: #8a8984;
        --sb-hover: #1e1e1c;
        --sb-active-bg: #13251a;
        --sb-active-ink: #55d06a;
        --panel-shadow: none;
    }
    html.sidebar-collapsed { --sb-width: var(--sb-width-collapsed); }

    *, ::before, ::after { box-sizing: border-box; }
    a { text-decoration: none; }

    /* "html body.shell" outranks the body rules each page loads with
       Admin/partials/theme (background, bottom padding for the help chat). */
    html body.shell {
        margin: 0;
        padding: var(--shell-gap) var(--shell-gap) var(--shell-gap) calc(var(--sb-width) + 2 * var(--shell-gap));
        min-height: 100vh;
        background: var(--canvas);
        font-family: var(--body-font);
        font-size: var(--normal-font-size);
        transition: padding-left .18s ease;
        transform: none !important;
    }
    /* Phones (window scrolling): keeps scrolled content from showing in
       the gap above the top bar. */
    body.shell::before {
        content: "";
        position: fixed;
        inset: 0 0 auto;
        height: var(--shell-gap);
        background: var(--canvas);
        z-index: 95;
    }

    /* ===== PAGE PANEL ===== */
    .app-panel {
        min-height: calc(100vh - 2 * var(--shell-gap));
        display: flex;
        flex-direction: column;
        background: var(--surface, #f7f8fc);
        border: 1px solid var(--border, #e8eaf0);
        border-radius: 10px;
        box-shadow: var(--panel-shadow);
    }
    .header.app-header {
        position: sticky;
        top: var(--shell-gap);
        z-index: 96;
        height: var(--header-height);
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 16px;
        background: var(--card, #fff);
        border-bottom: 1px solid var(--border, #e8eaf0);
        border-radius: 10px 10px 0 0;
    }
    .header_toggle {
        display: none;              /* phones only: opens the menu */
        padding: 6px;
        border: 0;
        border-radius: 6px;
        background: none;
        color: var(--nav-text);
        cursor: pointer;
    }
    .header_toggle:hover { color: var(--nav-text-strong); background: var(--nav-hover-bg); }
    .header_toggle svg { width: 22px; height: 22px; display: block; }
    .header_actions { display: flex; align-items: center; gap: 2px; margin-left: auto; min-width: 0; }
    .header_actions .header-search { margin-right: 14px; }
    .theme-toggle-btn { font-size: 1.2rem; }

    main#main-content { flex: 1; padding: 24px 28px 5.5rem; min-width: 0; }
    body.shell:has(.fab-container) main#main-content { padding-bottom: 10rem; }
    main:focus { outline: none; }

    /* "Skip to main content": first thing a keyboard user reaches, hidden until it has focus. */
    .skip-link {
        position: fixed; top: 8px; left: 8px; z-index: 3000;
        padding: 10px 16px; border-radius: 6px;
        background: var(--first-color); color: #fff; font-weight: 600; font-size: .9rem;
        transform: translateY(-150%); transition: transform .15s;
    }
    .skip-link:focus { transform: none; color: #fff; }

    /* Laptops: the page panel stays in place like the sidebar, all four
       corners in view, and only the page inside it scrolls (the user asked,
       2026-10-25). Scripts that follow scrolling use window.appScroll from
       Admin/home. Phones keep the window scrolling (below), where the
       browser bar hides on scroll. */
    @media (min-width: 768px) {
        html body.shell { height: 100vh; overflow: hidden; }
        .app-panel { height: 100%; min-height: 0; }
        .header.app-header { position: relative; top: auto; flex-shrink: 0; }
        main#main-content {
            overflow-y: auto;
            min-height: 0;
            border-radius: 0 0 10px 10px;
            scrollbar-width: thin;
            scrollbar-color: var(--border, #e8eaf0) transparent;
        }
    }

    /* ===== SIDEBAR PANEL ===== */
    .app-sidebar {
        position: fixed;
        top: var(--shell-gap);
        bottom: var(--shell-gap);
        left: var(--shell-gap);
        width: var(--sb-width);
        display: flex;
        flex-direction: column;
        background: var(--sb-bg);
        border: 1px solid var(--sb-border);
        border-radius: 10px;
        box-shadow: var(--panel-shadow);
        color: var(--sb-ink);
        z-index: var(--z-fixed);
        transition: width .18s ease;
    }
    .sb-brand {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 14px 10px; min-height: 62px;
        color: var(--sb-ink); border-radius: 10px 10px 0 0;
    }
    .sb-brand:hover { color: var(--sb-ink); }
    .sb-logo {
        width: 34px; height: 34px; flex-shrink: 0;
        border-radius: 50%; object-fit: cover;
        background: #fff; border: 1px solid var(--sb-border);
    }
    .sb-brand-text { min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
    .sb-brand-text b { font-size: .95rem; font-weight: 700; letter-spacing: -.01em; white-space: nowrap; }
    .sb-brand-text small { font-size: .72rem; color: var(--sb-muted); white-space: nowrap; }

    .sb-scroll {
        flex: 1; min-height: 0;
        overflow-y: auto; overflow-x: hidden;
        padding: 6px 10px 12px;
        display: flex; flex-direction: column; gap: 14px;
        scrollbar-width: thin; scrollbar-color: var(--sb-border) transparent;
    }
    .sb-label {
        margin: 0 0 4px; padding: 0 8px;
        font-size: .66rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        color: var(--sb-muted); white-space: nowrap;
    }
    .sb-link {
        position: relative;
        display: flex; align-items: center; gap: 10px;
        min-height: 36px; padding: 0 9px; margin-bottom: 2px;
        border-radius: 5px;
        color: var(--sb-ink-2);
        font-size: .86rem; font-weight: 500;
        white-space: nowrap;
        transition: background .12s, color .12s;
    }
    .sb-link:hover { background: var(--sb-hover); color: var(--sb-ink); }
    .sb-link.active { background: var(--sb-active-bg); color: var(--sb-active-ink); font-weight: 600; }
    .sb-link:focus-visible, .sb-brand:focus-visible, .sb-collapse:focus-visible, .user-profile:focus-visible {
        outline: 2px solid var(--first-color); outline-offset: -2px;
    }
    .sb-icon {
        width: 18px; height: 18px; flex-shrink: 0;
        fill: none; stroke: currentColor; stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round;
    }
    .sb-name { overflow: hidden; text-overflow: ellipsis; }
    .sb-count {
        margin-left: auto; min-width: 20px; height: 18px; padding: 0 6px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 4px; font-size: .7rem; font-weight: 700; font-variant-numeric: tabular-nums;
        background: var(--warning-dim, #fffbeb); color: var(--warning, #b45309);
    }
    .sb-link.active .sb-count { background: rgba(255, 255, 255, .2); color: inherit; }

    /* ===== ACCOUNT MENU + COLLAPSE (bottom of the sidebar) ===== */
    .sb-foot {
        position: relative;
        display: flex; align-items: center; gap: 4px;
        padding: 8px;
        border-top: 1px solid var(--sb-border);
        flex-shrink: 0;
    }
    .user-profile {
        flex: 1; min-width: 0;
        display: flex; align-items: center; gap: .6rem;
        padding: 6px; border: 0; border-radius: 6px;
        background: none; color: inherit; font: inherit; text-align: left; cursor: pointer;
        transition: background .12s;
    }
    .user-profile:hover { background: var(--sb-hover); }
    .user-profile img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--sb-border); flex-shrink: 0; }
    .user-info { display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
    .user-name { display: block; font-weight: 600; font-size: 13px; color: var(--sb-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { display: block; font-size: 11px; color: var(--sb-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-caret { margin-left: auto; color: var(--sb-muted); font-size: .9rem; flex-shrink: 0; }
    .sb-collapse {
        width: 32px; height: 32px; flex-shrink: 0;
        display: grid; place-items: center;
        border: 0; border-radius: 6px; background: none; color: var(--sb-muted); cursor: pointer;
        transition: background .12s, color .12s, transform .18s;
    }
    .sb-collapse:hover { background: var(--sb-hover); color: var(--sb-ink); }

    .user-dropdown {
        position: absolute; bottom: calc(100% + 6px); left: 8px;
        width: 200px;
        background: #fff; border: 1px solid var(--sidebar-border); border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .1);
        overflow: hidden; display: none; z-index: 999;
    }
    .user-dropdown.show { display: block; }
    .user-dropdown a { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #333; font-size: 14px; transition: background .15s; }
    .user-dropdown a:hover { background: #f2f7f4; color: var(--first-color); }
    .user-dropdown a.signout { color: #b3261e; border-top: 1px solid #eee; }
    .user-dropdown a.signout:hover { background: #fdecea; color: #b3261e; }
    .user-dropdown i { font-size: 16px; width: 16px; text-align: center; }
    .user-dropdown .ud-item {
        display: flex; align-items: center; gap: 10px; width: 100%;
        padding: 11px 16px; border: 0; border-top: 1px solid #eee; background: none;
        color: #333; font: inherit; font-size: 14px; text-align: left; cursor: pointer;
        transition: background .15s;
    }
    .user-dropdown .ud-item:hover { background: #f2f7f4; color: var(--first-color); }
    .user-dropdown .ud-item svg { width: 16px; height: 16px; flex-shrink: 0; }
    .user-dropdown .ud-item .kb-key { margin-left: auto; }
    [data-theme="dark"] .user-dropdown .ud-item { color: var(--text-1); border-top-color: var(--border); }
    [data-theme="dark"] .user-dropdown .ud-item:hover { background: var(--nav-hover-bg); color: var(--primary); }

    /* ===== KEYBOARD SHORTCUTS (partials/app-shell/shortcuts) ===== */
    kbd.kb-key {
        display: inline-block; min-width: 24px; padding: 1px 7px;
        border: 1px solid var(--border, #e8eaf0); border-bottom-width: 2px; border-radius: 4px;
        background: var(--surface, #f7f8fc); color: var(--text-1, #111827);
        font: 600 .76rem/1.5 var(--body-font); text-align: center; box-shadow: none;
    }
    .kb-dialog {
        width: min(560px, calc(100vw - 32px)); max-height: calc(100vh - 48px);
        padding: 0; border: 1px solid var(--border, #e8eaf0); border-radius: 10px;
        background: var(--card, #fff); color: var(--text-1, #111827);
        box-shadow: 0 24px 60px -20px rgba(0, 0, 0, .45);
    }
    .kb-dialog::backdrop { background: rgba(15, 23, 42, .45); }
    .kb-inner { padding: 20px 22px 18px; display: flex; flex-direction: column; gap: 18px; }
    .kb-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .kb-head h2 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .kb-close { width: 32px; height: 32px; border: 0; border-radius: 6px; background: none; color: var(--text-2, #4b5563); font-size: 22px; line-height: 1; cursor: pointer; }
    .kb-close:hover { background: var(--nav-hover-bg); color: var(--text-1, #111827); }
    .kb-close:focus-visible { outline: 2px solid var(--first-color); outline-offset: 1px; }
    .kb-section h3 { margin: 0 0 6px; font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-3, #646b78); }
    .kb-list { margin: 0; }
    .kb-list > div { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 7px 0; border-top: 1px solid var(--border, #e8eaf0); font-size: .88rem; }
    .kb-list dt { order: 2; flex-shrink: 0; font-weight: 400; color: var(--text-2, #4b5563); white-space: nowrap; }
    .kb-list dd { margin: 0; }
    .kb-foot { margin: 0; font-size: .8rem; color: var(--text-3, #646b78); }

    /* "G then a letter": after G, each page's letter shows in the menu for a moment. */
    .sb-link .sb-key { display: none; margin-left: auto; flex-shrink: 0; }   /* outranks kbd.kb-key's display */
    html.kb-go .sb-link .sb-key { display: inline-block; }
    html.kb-go .sb-count { display: none; }
    html.sidebar-collapsed.kb-go .sb-key { position: absolute; top: 1px; right: 3px; min-width: 0; padding: 0 4px; font-size: .62rem; }
    .kb-go-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 28px; }
    @media (max-width: 560px) { .kb-go-grid { grid-template-columns: minmax(0, 1fr); } }

    /* The "/" hint at the end of the search box (partials/header-search). */
    .header-search .header-search-field input { padding-right: 44px; }   /* outranks the partial's own later rule */
    .header-search-key { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; }
    .header-search-field:focus-within .header-search-key,
    .header-search input:not(:placeholder-shown) + .header-search-key { display: none; }

    /* ===== COLLAPSED: icons only (laptops; remembered) =====
       The names are hidden visually only, so every icon link still has its
       name for a screen reader (never visibility:hidden or display:none). */
    html.sidebar-collapsed .sb-brand { justify-content: center; padding-left: 0; padding-right: 0; }
    html.sidebar-collapsed :is(.sb-brand-text, .sb-name, .user-info) {
        position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap;
    }
    html.sidebar-collapsed .user-caret { display: none; }
    html.sidebar-collapsed .sb-scroll { padding-left: 8px; padding-right: 8px; }
    html.sidebar-collapsed .sb-label { height: 1px; margin: 4px 10px 6px; padding: 0; overflow: hidden; color: transparent; background: var(--sb-border); }
    html.sidebar-collapsed .sb-link { justify-content: center; padding: 0; }
    html.sidebar-collapsed .sb-count {
        position: absolute; top: 6px; right: 9px;
        min-width: 8px; width: 8px; height: 8px; padding: 0; border-radius: 50%;
        font-size: 0; background: var(--warning, #b45309);
    }
    html.sidebar-collapsed .sb-foot { flex-direction: column; }
    html.sidebar-collapsed .user-profile { flex: none; justify-content: center; }
    html.sidebar-collapsed .sb-collapse { transform: rotate(180deg); }

    /* ===== NOTIFICATION BELL (top bar, right side) ===== */
    .notif-bell { position: relative; }
    .notif-btn {
        background: none; border: none; cursor: pointer; display: flex;
        color: var(--nav-text); font-size: 1.25rem; padding: 6px;
        border-radius: 8px; position: relative;
        transition: background .15s, color .15s;
    }
    .notif-btn:hover { background: var(--nav-hover-bg); color: var(--nav-text-strong); }
    .notif-badge {
        position: absolute; top: 0; right: 0;
        min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px;
        background: #dc2626; color: #fff; font-size: 10px; font-weight: 700;
        display: none; align-items: center; justify-content: center; line-height: 1;
    }
    .notif-badge.on { display: flex; }
    .notif-panel {
        position: absolute; top: calc(100% + 10px); right: 0; width: 330px;
        background: #fff; border: 1px solid var(--sidebar-border); border-radius: 10px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .12); display: none; z-index: 1100; overflow: hidden;
    }
    .notif-panel.open { display: block; }
    .notif-head {
        display: flex; justify-content: space-between; align-items: center;
        padding: 11px 14px; border-bottom: 1px solid var(--sidebar-border);
        font-weight: 600; font-size: .85rem; color: var(--nav-text-strong);
    }
    .notif-head button { background: none; border: none; color: var(--first-color); font-size: .75rem; font-weight: 600; cursor: pointer; }
    .notif-list { max-height: 360px; overflow-y: auto; }
    .notif-item {
        display: flex; gap: 10px; padding: 11px 14px; width: 100%;
        border: 0; border-bottom: 1px solid #f2f4f8; background: none;
        font: inherit; color: inherit; text-align: left;
        cursor: pointer; transition: background .12s;
    }
    .notif-body, .notif-title, .notif-when { display: block; }
    .notif-item:hover { background: var(--nav-hover-bg); }
    .notif-item:last-child { border-bottom: none; }
    .notif-item.unread { background: #e9f5ee; }
    .notif-ico { width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 15px; }
    .notif-body { min-width: 0; }
    .notif-title { font-size: .8rem; font-weight: 600; color: var(--nav-text-strong); }
    .notif-msg { font-size: .75rem; color: var(--nav-text); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .notif-item:focus-visible { outline: 2px solid var(--first-color); outline-offset: -2px; }
    .notif-when { font-size: .68rem; color: #646b78; margin-top: 2px; }
    .notif-empty { padding: 30px 14px; text-align: center; color: #646b78; font-size: .8rem; }

    /* ===== PHONES: the page fills the screen, the menu slides in ===== */
    .sb-backdrop { position: fixed; inset: 0; z-index: 99; background: rgba(0, 0, 0, .35); }
    @media (max-width: 767.98px) {
        :root { --shell-gap: 8px; }
        html body.shell { padding: var(--shell-gap); }
        main#main-content { padding: 18px 14px 5.5rem; }
        .header_toggle { display: flex; }

        /* The collapsed state is for laptops; the phone menu always shows names. */
        .app-sidebar,
        html.sidebar-collapsed .app-sidebar { width: min(280px, calc(100vw - 48px)); }
        html.sidebar-collapsed :is(.sb-brand-text, .sb-name, .user-info) { position: static; width: auto; height: auto; overflow: visible; clip: auto; }
        html.sidebar-collapsed .sb-brand { justify-content: flex-start; padding: 14px 14px 10px; }
        html.sidebar-collapsed .sb-label { height: auto; margin: 0 0 4px; padding: 0 8px; color: var(--sb-muted); background: none; }
        html.sidebar-collapsed .sb-link { justify-content: flex-start; padding: 0 9px; }
        html.sidebar-collapsed .sb-count { position: static; width: auto; min-width: 20px; height: 18px; padding: 0 6px; border-radius: 4px; font-size: .7rem; background: var(--warning-dim, #fffbeb); }
        html.sidebar-collapsed .sb-foot { flex-direction: row; }
        html.sidebar-collapsed .user-profile { flex: 1; justify-content: flex-start; }
        html.sidebar-collapsed .user-caret { display: inline; }
        .sb-collapse { display: none; }

        /* Off screen and out of the Tab order until opened. */
        .app-sidebar {
            transform: translateX(calc(-100% - 2 * var(--shell-gap)));
            visibility: hidden;
            transition: transform .2s ease, visibility 0s linear .2s;
            z-index: 1050;
        }
        html.sidebar-open .app-sidebar { transform: none; visibility: visible; transition: transform .2s ease; box-shadow: 0 12px 40px rgba(0, 0, 0, .3); }
        html.sidebar-open .sb-backdrop { z-index: 1049; }
    }
    @media (prefers-reduced-motion: reduce) {
        html body.shell, .app-sidebar, .sb-collapse { transition: none; }
    }

    /* Light <-> dark: the whole screen cross-fades once, quickly
       (partials/theme-toggle starts it with document.startViewTransition). */
    ::view-transition-old(root),
    ::view-transition-new(root) {
        animation-duration: .2s;
        animation-timing-function: ease-out;
    }

    /* Force modals to cover the real viewport (vw/vh instead of %, so it
       can't be shrunk by whatever containing block is confusing position:fixed
       on this page). Dialog is centered via absolute + transform instead of
       display:flex, since flex-centering depended on the .show class — and
       Bootstrap removes .show at the START of the closing fade, which made
       the dialog snap out of place before the fade-out finished. */
    .modal {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .modal-dialog {
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-backdrop {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
    }
</style>
