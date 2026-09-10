{{--
    In-app help assistant. Floating launcher (bottom-right; lifted above the
    green FAB on the folder pages via body:has(.fab-container)) + a chat panel.
    Talks to HelpBotController@ask, which grounds every answer on
    resources/help/officer-guide.md. Renders nothing when no Gemini key is set.

    Uses the design tokens from Admin/partials/theme.blade.php, so it follows
    light/dark mode automatically. Include once, just before </body>.
--}}
@if (config('services.gemini.key'))
<style>
    .hb-launcher {
        position: fixed; right: 1.5rem; bottom: 1.5rem; z-index: 1090;
        display: flex; align-items: center; gap: 9px;
        padding: 11px 16px 11px 13px; border: none; border-radius: 999px;
        background: var(--primary); color: #fff;
        font: 600 13px/1 'Inter', sans-serif; cursor: pointer;
        box-shadow: 0 6px 20px rgba(0,0,0,.18);
        transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
    }
    .hb-launcher:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 10px 26px rgba(0,0,0,.22); }
    .hb-launcher i { font-size: 1.15rem; }
    .hb-launcher.hb-hidden { display: none; }

    /* On pages with the bottom-right green FAB (Folders, folder view), sit
       above it so the two don't stack on the same spot. */
    body:has(.hb-launcher) .fab-container { bottom: 6rem; }

    .hb-panel {
        position: fixed; right: 1.5rem; bottom: 1.5rem; z-index: 1091;
        width: 360px; max-width: calc(100vw - 3rem);
        height: 520px; max-height: calc(100vh - 3rem);
        display: none; flex-direction: column;
        background: var(--card); border: 1px solid var(--border);
        border-radius: 16px; overflow: hidden;
        box-shadow: 0 18px 48px rgba(0,0,0,.28);
    }
    .hb-panel.hb-open { display: flex; }

    .hb-head {
        display: flex; align-items: center; gap: 10px;
        padding: 13px 14px; border-bottom: 1px solid var(--border);
        background: var(--card);
    }
    .hb-head .hb-avatar {
        width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--primary-dim); color: var(--primary); font-size: 1.05rem;
    }
    .hb-head .hb-title { font-weight: 600; font-size: .9rem; color: var(--text-1); line-height: 1.2; }
    .hb-head .hb-sub { font-size: .7rem; color: var(--text-3); }
    .hb-head .hb-close {
        margin-left: auto; background: none; border: none; cursor: pointer;
        color: var(--text-3); font-size: 1.3rem; line-height: 1; padding: 2px 6px;
    }
    .hb-head .hb-close:hover { color: var(--text-1); }

    .hb-log { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
    .hb-msg { max-width: 85%; font-size: .82rem; line-height: 1.5; border-radius: 12px; padding: 9px 12px; word-wrap: break-word; }
    .hb-msg.user { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 4px; }
    .hb-msg.bot  { align-self: flex-start; background: var(--surface); color: var(--text-1); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
    .hb-msg.bot strong { font-weight: 600; }
    .hb-msg.bot ol, .hb-msg.bot ul { margin: 4px 0 0; padding-left: 18px; }
    .hb-msg.bot code { background: var(--nav-hover-bg); padding: 1px 4px; border-radius: 4px; font-size: .95em; }
    .hb-msg.typing { color: var(--text-3); font-style: italic; }

    .hb-feedback { align-self: flex-start; display: flex; gap: 6px; margin-top: -4px; }
    .hb-feedback button {
        background: none; border: 1px solid var(--border); border-radius: 6px;
        color: var(--text-3); cursor: pointer; font-size: .7rem; padding: 2px 7px;
    }
    .hb-feedback button:hover { color: var(--text-1); border-color: var(--text-3); }
    .hb-feedback.done { color: var(--text-3); font-size: .7rem; padding: 2px 0; }

    .hb-form { display: flex; gap: 8px; padding: 12px; border-top: 1px solid var(--border); background: var(--card); }
    .hb-form input {
        flex: 1; border: 1px solid var(--border); border-radius: 999px;
        padding: 9px 14px; font-size: .82rem; background: var(--surface); color: var(--text-1);
        outline: none;
    }
    .hb-form input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-dim); }
    .hb-form button {
        flex-shrink: 0; width: 38px; border: none; border-radius: 50%;
        background: var(--primary); color: #fff; cursor: pointer; font-size: 1rem;
    }
    .hb-form button:disabled { opacity: .5; cursor: default; }

    @media (max-width: 480px) {
        .hb-panel { left: 0; right: 0; bottom: 0; width: 100%; max-width: 100%; height: 80vh; border-radius: 16px 16px 0 0; }
        .hb-launcher span { display: none; }
        .hb-launcher { padding: 12px; }
    }
</style>

<button type="button" class="hb-launcher" id="hbLauncher" aria-label="Ask for help">
    <i class='bx bx-message-rounded-dots'></i><span>Need help?</span>
</button>

<div class="hb-panel" id="hbPanel" role="dialog" aria-label="Help assistant">
    <div class="hb-head">
        <div class="hb-avatar"><i class='bx bx-bot'></i></div>
        <div>
            <div class="hb-title">Help assistant</div>
            <div class="hb-sub">Answers about using QSU-FMAS</div>
        </div>
        <button type="button" class="hb-close" id="hbClose" aria-label="Close">&times;</button>
    </div>

    <div class="hb-log" id="hbLog"></div>

    <form class="hb-form" id="hbForm" autocomplete="off">
        <input type="text" id="hbInput" maxlength="500" placeholder="e.g. How do I archive a folder?" aria-label="Your question">
        <button type="submit" id="hbSend" aria-label="Send"><i class='bx bx-send'></i></button>
    </form>
</div>

<script>
(function () {
    var launcher = document.getElementById('hbLauncher'),
        panel    = document.getElementById('hbPanel'),
        closeBtn = document.getElementById('hbClose'),
        log      = document.getElementById('hbLog'),
        form     = document.getElementById('hbForm'),
        input    = document.getElementById('hbInput'),
        sendBtn  = document.getElementById('hbSend');

    var csrf   = document.querySelector('meta[name="csrf-token"]').content,
        askUrl = "{{ route('helpbot.ask') }}",
        fbUrl  = "{{ route('helpbot.feedback') }}",
        STORE  = 'hbConversation';

    // history: [{role:'user'|'model', text}] - kept for follow-up context and
    // restored across page loads within the same tab.
    var history = [];
    try { history = JSON.parse(sessionStorage.getItem(STORE) || '[]'); } catch (e) {}

    function save() {
        try { sessionStorage.setItem(STORE, JSON.stringify(history.slice(-12))); } catch (e) {}
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Minimal, safe markdown: escape first, then re-introduce a few tags.
    function render(text) {
        var html = esc(text)
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
        var lines = html.split('\n'), out = [], list = null;
        lines.forEach(function (ln) {
            var ol = ln.match(/^\s*\d+\.\s+(.*)$/),
                ul = ln.match(/^\s*[-*]\s+(.*)$/);
            if (ol) { if (list !== 'ol') { if (list) out.push('</' + list + '>'); out.push('<ol>'); list = 'ol'; } out.push('<li>' + ol[1] + '</li>'); }
            else if (ul) { if (list !== 'ul') { if (list) out.push('</' + list + '>'); out.push('<ul>'); list = 'ul'; } out.push('<li>' + ul[1] + '</li>'); }
            else { if (list) { out.push('</' + list + '>'); list = null; } out.push(ln); }
        });
        if (list) out.push('</' + list + '>');
        return out.join('\n').replace(/\n{2,}/g, '<br><br>').replace(/\n/g, '<br>');
    }

    function bubble(role, text) {
        var el = document.createElement('div');
        el.className = 'hb-msg ' + role;
        el.innerHTML = role === 'bot' ? render(text) : esc(text);
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }

    function feedbackRow(logId) {
        var row = document.createElement('div');
        row.className = 'hb-feedback';
        row.innerHTML = '<button data-v="1">👍 Helpful</button><button data-v="0">👎 Not really</button>';
        row.addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) return;
            fetch(fbUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ log_id: logId, helpful: b.dataset.v === '1' }),
            }).catch(function () {});
            row.className = 'hb-feedback done';
            row.textContent = 'Thanks for the feedback.';
        });
        log.appendChild(row);
        log.scrollTop = log.scrollHeight;
    }

    function greet() {
        log.innerHTML = '';
        if (!history.length) {
            bubble('bot', "Hi! Ask me anything about using QSU-FMAS — folders, uploading, the scan inbox, archives, announcements, and so on.");
            return;
        }
        history.forEach(function (t) { bubble(t.role === 'model' ? 'bot' : 'user', t.text); });
    }

    var opened = false;
    function open() {
        panel.classList.add('hb-open');
        launcher.classList.add('hb-hidden');
        if (!opened) { greet(); opened = true; }
        setTimeout(function () { input.focus(); }, 50);
    }
    function close() {
        panel.classList.remove('hb-open');
        launcher.classList.remove('hb-hidden');
    }

    launcher.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('hb-open')) close(); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = input.value.trim();
        if (!q) return;

        // Context = the turns BEFORE this question (the controller appends the
        // question itself), so it isn't sent twice.
        var prior = history.slice(-6);

        bubble('user', q);
        history.push({ role: 'user', text: q });
        save();
        input.value = '';
        sendBtn.disabled = true;

        var typing = bubble('bot', 'Typing…');
        typing.classList.add('typing');

        fetch(askUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ message: q, history: prior }),
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            typing.remove();
            var reply = res.j.reply || "Sorry, something went wrong.";
            bubble('bot', reply);
            history.push({ role: 'model', text: reply });
            save();
            if (res.ok && res.j.log_id) feedbackRow(res.j.log_id);
        })
        .catch(function () {
            typing.remove();
            bubble('bot', "Sorry, I couldn't reach the help service. Please try again.");
        })
        .finally(function () {
            sendBtn.disabled = false;
            input.focus();
        });
    });
})();
</script>
@endif
