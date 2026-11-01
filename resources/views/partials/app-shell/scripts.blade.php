{{--
    Sidebar behaviour for both officer layouts (see partials/app-shell/sidebar):
    - laptops: the button at the bottom collapses the menu to icons; the
      choice is remembered (localStorage "sidebarCollapsed", painted before
      first render by the snippet in Admin/home's <head>);
    - phones: the top-bar button slides the menu in; Escape, the backdrop
      or a resize back to a laptop width closes it;
    - the account menu (Profile / Sign Out) above the user's name.
--}}
<script>
(function () {
    var root = document.documentElement;
    var sidebar = document.getElementById('nav-bar');
    var openBtn = document.getElementById('header-toggle');
    var collapseBtn = document.getElementById('sidebar-collapse');
    var backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar || !openBtn || !collapseBtn || !backdrop) return;

    // ---- laptops: collapse to icons ----
    var syncCollapse = function () {
        var collapsed = root.classList.contains('sidebar-collapsed');
        var label = collapsed ? 'Expand menu' : 'Collapse menu';
        collapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        collapseBtn.setAttribute('aria-label', label);
        collapseBtn.title = label + ' ( [ )';
    };
    collapseBtn.addEventListener('click', function () {
        var collapsed = !root.classList.contains('sidebar-collapsed');
        root.classList.toggle('sidebar-collapsed', collapsed);
        try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
        syncCollapse();
    });
    syncCollapse();

    // ---- phones: slide-in menu ----
    var setOpen = function (open) {
        var wasOpen = root.classList.contains('sidebar-open');
        root.classList.toggle('sidebar-open', open);
        backdrop.hidden = !open;
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        openBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        if (open) {
            var current = sidebar.querySelector('.sb-link.active') || sidebar.querySelector('.sb-link');
            if (current) current.focus();
        } else if (wasOpen) {
            openBtn.focus();
        }
    };
    openBtn.addEventListener('click', function () { setOpen(!root.classList.contains('sidebar-open')); });
    backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.classList.contains('sidebar-open')) setOpen(false);
    });
    var phone = window.matchMedia('(max-width: 767.98px)');
    var onWidthChange = function () { if (!phone.matches && root.classList.contains('sidebar-open')) setOpen(false); };
    if (phone.addEventListener) phone.addEventListener('change', onWidthChange); else phone.addListener(onWidthChange);

    var isTyping = function (el) {
        return !!el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
    };
    var overlayOpen = function () {
        return document.querySelector('.modal.show, dialog[open], .dropdown-menu.show, .user-dropdown.show, .notif-panel.open, .header-search-dropdown.show');
    };

    // ---- keyboard scrolling of the page panel (laptops) ----
    // The window no longer scrolls, so the keys are handed to the page panel:
    // with nothing focused, by focusing it (the browser then scrolls it as
    // usual); with focus on the sidebar or top bar, by scrolling it directly,
    // leaving focus where it is. Space stays with a focused button (it presses it).
    var scrollKeys = ['ArrowDown', 'ArrowUp', 'PageDown', 'PageUp', 'Home', 'End', ' '];
    document.addEventListener('keydown', function (e) {
        if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey || scrollKeys.indexOf(e.key) === -1) return;
        var panel = window.appScroll && appScroll.panel();
        if (!panel) return;
        var el = document.activeElement;
        if (!el || el === document.body || el === document.documentElement) {
            panel.focus({ preventScroll: true });
            return;
        }
        if (panel.contains(el) || isTyping(el) || overlayOpen()) return;
        var page = panel.clientHeight * 0.9;
        var step = { ArrowDown: 40, ArrowUp: -40, PageDown: page, PageUp: -page }[e.key];
        if (step !== undefined) {
            e.preventDefault();
            panel.scrollBy({ top: step });
        } else if (e.key === 'Home' || e.key === 'End') {
            e.preventDefault();
            panel.scrollTo({ top: e.key === 'Home' ? 0 : panel.scrollHeight });
        }
    });

    // ---- shortcuts: ? list, / search, [ menu, G then a letter to open a page ----
    // (partials/app-shell/shortcuts; the letters come from App\Support\OfficerMenu
    // as data-go-key on each menu link)
    var goPending = false, goTimer = null;
    var endGo = function () {
        goPending = false;
        clearTimeout(goTimer);
        root.classList.remove('kb-go');
    };
    var shortcuts = document.getElementById('shortcutsDialog');
    var openShortcuts = function () { if (shortcuts && !shortcuts.open) shortcuts.showModal(); };
    if (shortcuts) {
        shortcuts.querySelector('[data-kb-close]').addEventListener('click', function () { shortcuts.close(); });
        // A click on the dark area around the list closes it.
        shortcuts.addEventListener('click', function (e) { if (e.target === shortcuts) shortcuts.close(); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey || isTyping(e.target)) {
            endGo();
            return;
        }
        var key = e.key.length === 1 ? e.key.toLowerCase() : e.key;
        // Second key after G: open that page; any other key just cancels.
        if (goPending) {
            endGo();
            var link = /^[a-z]$/.test(key) ? sidebar.querySelector('.sb-link[data-go-key="' + key + '"]') : null;
            if (link) {
                e.preventDefault();
                window.location.href = link.href;
            }
            return;
        }
        if (e.key === '?') {
            e.preventDefault();
            if (shortcuts && shortcuts.open) shortcuts.close(); else openShortcuts();
            return;
        }
        if (overlayOpen()) return;
        if (key === 'g') {
            e.preventDefault();
            goPending = true;
            root.classList.add('kb-go');      // shows each page's letter in the menu
            goTimer = setTimeout(endGo, 2000);
            return;
        }
        if (e.key === '/') {
            var search = document.getElementById('topbarSearchInput');
            if (search && search.offsetParent !== null) {   // hidden on narrow screens
                e.preventDefault();
                search.focus();
            }
        } else if (e.key === '[' && !phone.matches) {
            e.preventDefault();
            collapseBtn.click();
        }
    });

    // ---- account menu ----
    var profileToggle = document.getElementById('profileToggle');
    var userDropdown = document.getElementById('userDropdown');
    if (!profileToggle || !userDropdown) return;
    var setUserMenu = function (open) {
        userDropdown.classList.toggle('show', open);
        profileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    profileToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        setUserMenu(!userDropdown.classList.contains('show'));
    });
    document.addEventListener('click', function () { setUserMenu(false); });
    var shortcutsItem = document.getElementById('openShortcuts');
    if (shortcutsItem) {
        shortcutsItem.addEventListener('click', function (e) {
            e.stopPropagation();
            setUserMenu(false);
            openShortcuts();
        });
    }
    // Escape closes the menu and puts focus back on its button.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && userDropdown.classList.contains('show')) {
            setUserMenu(false);
            profileToggle.focus();
        }
    });
})();
</script>
