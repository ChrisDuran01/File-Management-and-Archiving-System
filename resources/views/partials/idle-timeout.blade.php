{{--
    Idle-timeout warning: officers frequently work on shared campus
    computers, and the session cookie otherwise only expires quietly after
    SESSION_LIFETIME (config/session.php, default 120 minutes) with no
    warning - long enough that a computer left logged in is a real risk.
    This surfaces a visible countdown well before that, and actually ends
    the session (not just a client-side redirect) if nobody responds.

    Included once near the end of the authenticated layouts (Admin.home,
    SuperAdmin.homeSuperAdmin) - same pattern as partials.help-bot.
--}}
<div id="idleTimeoutOverlay" hidden style="position:fixed; inset:0; z-index:2000; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; padding:16px;">
    <div style="background:var(--card,#fff); color:var(--text-1,#1a1a1a); border:1px solid var(--border,#e5e7eb); border-radius:var(--radius,14px); box-shadow:var(--shadow-sm,0 4px 20px rgba(0,0,0,.15)); max-width:380px; width:100%; padding:24px; text-align:center; font-family:'Inter',sans-serif;">
        <i class="fas fa-hourglass-half" style="font-size:1.6rem; color:var(--brand,#6d28d9); margin-bottom:10px; display:block;"></i>
        <h3 style="font-size:1.05rem; font-weight:700; margin:0 0 6px;">Still there?</h3>
        <p style="font-size:.85rem; color:var(--text-2,#6b7280); margin:0 0 16px;">
            You've been inactive for a while. For security on shared computers, you'll be
            signed out in <strong id="idleCountdown">60</strong>s unless you stay logged in.
        </p>
        <button type="button" id="idleStayBtn" style="width:100%; border:none; background:var(--brand,#6d28d9); color:#fff; padding:10px 16px; border-radius:20px; font-size:.85rem; font-weight:700; cursor:pointer;">
            Stay logged in
        </button>
    </div>
</div>

<form id="idleLogoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">
    @csrf
    <input type="hidden" name="idle" value="1">
</form>

<script>
(function () {
    // 15 min of no activity shows the warning; a further 60s of silence
    // after that actually logs the officer out - well inside the 120 min
    // SESSION_LIFETIME so this is what a shared computer actually hits.
    var WARN_AFTER_MS = 15 * 60 * 1000;
    var COUNTDOWN_SECONDS = 60;

    var overlay = document.getElementById('idleTimeoutOverlay');
    var countdownEl = document.getElementById('idleCountdown');
    var stayBtn = document.getElementById('idleStayBtn');
    var logoutForm = document.getElementById('idleLogoutForm');
    var keepAliveUrl = "{{ route('session.keepAlive') }}";

    var lastActivity = Date.now();
    var warningShown = false;
    var remaining = COUNTDOWN_SECONDS;
    var countdownTimer = null;

    // Broad activity (including passive mouse movement/scroll) keeps the
    // "are they idle at all" clock reset during normal work.
    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, function () { lastActivity = Date.now(); }, { passive: true });
    });

    // Once the warning is already showing, only a deliberate action
    // dismisses it (a click, a keypress, a tap) - not ambient mouse jitter
    // from someone merely being near the machine - since the whole point
    // is confirming a person is actually there.
    ['mousedown', 'keydown', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, function () {
            if (warningShown) dismissWarning();
        });
    });

    function showWarning() {
        warningShown = true;
        remaining = COUNTDOWN_SECONDS;
        overlay.hidden = false;
        countdownEl.textContent = remaining;

        countdownTimer = setInterval(function () {
            remaining--;
            countdownEl.textContent = remaining;
            if (remaining <= 0) {
                clearInterval(countdownTimer);
                logoutForm.submit();
            }
        }, 1000);
    }

    function dismissWarning() {
        warningShown = false;
        overlay.hidden = true;
        clearInterval(countdownTimer);
        lastActivity = Date.now();

        // Touches the session server-side (and confirms it's still valid -
        // a 401 here means it already expired some other way) rather than
        // just resetting the client-side clock and hoping.
        fetch(keepAliveUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        }).then(function (r) {
            if (r.status === 401) window.location.href = '/login';
        }).catch(function () { /* transient - the next idle check tries again */ });
    }

    stayBtn.addEventListener('click', dismissWarning);

    setInterval(function () {
        if (!warningShown && Date.now() - lastActivity >= WARN_AFTER_MS) {
            showWarning();
        }
    }, 5000);
})();
</script>
