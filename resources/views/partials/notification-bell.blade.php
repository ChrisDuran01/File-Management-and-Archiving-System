{{--
    Header notification bell, shared by the authenticated layouts
    (Admin.home, SuperAdmin.homeSuperAdmin). Polls the JSON endpoint for
    the unread badge + recent list (same pattern as the backup progress
    poller), marks items read on click, then follows their link.
--}}
<div class="notif-bell">
    {{-- The unread count is part of the button's name (set by render()
         below), so the visual badge is hidden from screen readers. --}}
    <button type="button" class="notif-btn" id="notifBtn" aria-label="Notifications"
            aria-expanded="false" aria-controls="notifPanel">
        <i class='bx bx-bell' aria-hidden="true"></i>
        <span class="notif-badge" id="notifBadge" aria-hidden="true"></span>
    </button>
    <div class="notif-panel" id="notifPanel" role="region" aria-label="Notifications">
        <div class="notif-head">
            <span>Notifications</span>
            <button type="button" id="notifMarkAll">Mark all read</button>
        </div>
        <div class="notif-list" id="notifList">
            <div class="notif-empty">No notifications yet.</div>
        </div>
    </div>
</div>

<script>
(function () {
    const btn = document.getElementById('notifBtn'),
          panel = document.getElementById('notifPanel'),
          list = document.getElementById('notifList'),
          badge = document.getElementById('notifBadge'),
          markAll = document.getElementById('notifMarkAll');
    if (!btn || !panel || !list || !badge) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const POLL_MS = 30000;

    let latest = null;    // the server's last answer
    let shownHtml = null; // what the list currently shows
    let timer = null;

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    function listHtml(notifications) {
        if (!notifications.length) {
            return '<div class="notif-empty">No notifications yet.</div>';
        }

        // Buttons (not divs) so each item can be reached with Tab and opened
        // with Enter; spans because a button may only hold inline content.
        return notifications.map(n =>
            '<button type="button" class="notif-item' + (n.read ? '' : ' unread') + '" data-id="' + esc(n.id) + '" data-link="' + esc(n.link) + '">' +
                '<span class="notif-ico" style="background:' + esc(n.color) + '1a;color:' + esc(n.color) + ';" aria-hidden="true"><i class="' + esc(n.icon) + '"></i></span>' +
                '<span class="notif-body">' +
                    (n.read ? '' : '<span class="sr-only">Unread: </span>') +
                    '<span class="notif-title">' + esc(n.title) + '</span>' +
                    '<span class="notif-msg">' + esc(n.message) + '</span>' +
                    '<span class="notif-when">' + esc(n.when) + '</span>' +
                '</span>' +
            '</button>'
        ).join('');
    }

    // The list is rebuilt only while the panel is open, and only when it
    // would look different: a closed panel just keeps the latest answer for
    // when it opens, and rebuilding an unchanged list under the pointer can
    // swallow the click an officer is in the middle of.
    function drawList() {
        if (!latest || !panel.classList.contains('open')) return;

        const html = listHtml(latest.notifications);
        if (html === shownHtml) return;

        list.innerHTML = html;
        shownHtml = html;
    }

    function render(data) {
        latest = data;

        const count = data.unread > 99 ? '99+' : String(data.unread);
        if (badge.textContent !== count) badge.textContent = count;
        badge.classList.toggle('on', data.unread > 0);
        btn.setAttribute('aria-label', data.unread > 0 ? 'Notifications, ' + count + ' unread' : 'Notifications');

        drawList();
    }

    function poll() {
        fetch("{{ route('notifications.index') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(render).catch(() => {});
    }

    // A tab in the background stops asking; coming back to it asks straight
    // away. Saves a request every 30s for every tab left open on a shared PC.
    function startPolling() {
        stopPolling();
        poll();
        timer = setInterval(poll, POLL_MS);
    }

    function stopPolling() {
        clearInterval(timer);
        timer = null;
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopPolling();
        else startPolling();
    });

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

    function setOpen(open) {
        panel.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!panel.classList.contains('open'));
        if (panel.classList.contains('open')) {
            drawList();
            poll();
        }
    });

    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target)) setOpen(false);
    });

    // Escape closes the panel and puts focus back on the bell.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            setOpen(false);
            btn.focus();
        }
    });

    if (!document.hidden) startPolling();
})();
</script>
