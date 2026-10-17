{{--
    The website's Share dialog - make a link to one file for someone without
    an account, copy it, and see or turn off the links already made (see
    ShareLinkController). Included by the pages that offer it (Admin/showFolder,
    Admin/preview); any button there opens it with:

        <button type="button" data-share-file
                data-share-name="{{ $file->filename }}"
                data-share-url="{{ route('share-links.index', $file->id) }}">

    A native <dialog> like partials/confirm-dialog, so it doesn't depend on
    the page loading Bootstrap's script.
--}}
<style>
    .share-dialog {
        width: min(480px, calc(100vw - 32px));
        padding: 0;
        border: 1px solid var(--border, #e8eaf0);
        border-radius: 16px;
        background: var(--card, #fff);
        color: var(--text-1, #111827);
        box-shadow: 0 24px 64px rgba(15, 23, 42, .28);
    }
    .share-dialog::backdrop { background: rgba(15, 23, 42, .5); }
    .share-dialog[open] { animation: share-in .16s ease-out; }
    @keyframes share-in { from { opacity: 0; transform: translateY(8px) scale(.98); } }
    .share-box { padding: 22px 24px 24px; display: flex; flex-direction: column; gap: 16px; }

    .share-head { display: flex; align-items: flex-start; gap: 12px; }
    .share-head > div { flex: 1; min-width: 0; }
    .share-title { margin: 0; font-size: 1.05rem; font-weight: 600; }
    .share-file { margin: 2px 0 0; font-size: .85rem; color: var(--text-2, #6b7280); overflow-wrap: anywhere; }
    .share-close {
        width: 34px; height: 34px; flex-shrink: 0;
        display: grid; place-items: center;
        border: 0; border-radius: 8px; background: transparent; color: var(--text-2, #6b7280);
    }
    .share-close:hover { background: var(--nav-hover-bg, #f4f6f9); color: var(--text-1, #111827); }

    .share-intro { margin: 0; font-size: .85rem; line-height: 1.5; color: var(--text-2, #6b7280); }

    .share-note { margin: 0; padding: 10px 12px; border-radius: 10px; font-size: .82rem; line-height: 1.45; }
    .share-note.amber { background: var(--warning-dim, #fffbeb); color: var(--warning, #b45309); }
    .share-note.green { background: var(--success-dim, #e9f5ee); color: var(--success-text, #046322); }
    .share-note.red { background: var(--danger-dim, #fef2f2); color: var(--danger, #dc2626); }

    .share-make { display: flex; align-items: flex-end; gap: 10px; }
    .share-field { flex: 1; display: flex; flex-direction: column; gap: 5px; min-width: 0; }
    .share-field label { font-size: .78rem; font-weight: 600; }
    .share-field select,
    .share-field input {
        height: 40px; width: 100%;
        padding: 0 10px;
        border: 1px solid var(--border, #e8eaf0); border-radius: 8px;
        background: var(--surface, #f7f8fc); color: var(--text-1, #111827);
        font-size: .875rem;
    }
    .share-url-row { display: flex; gap: 8px; }

    .share-btn {
        min-height: 40px; padding: 0 14px;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        border: 1px solid var(--border, #e8eaf0); border-radius: 8px;
        background: var(--card, #fff); color: var(--text-1, #111827);
        font-size: .85rem; font-weight: 600; white-space: nowrap; cursor: pointer;
    }
    .share-btn:hover { background: var(--nav-hover-bg, #f4f6f9); }
    /* Fixed colours: keep white text readable in light and dark mode. */
    .share-btn.primary { border-color: transparent; background: #058028; color: #fff; }
    .share-btn.primary:hover { background: #046322; }
    .share-btn.small { min-height: 32px; padding: 0 10px; font-size: .8rem; }
    .share-btn.danger { color: var(--danger, #dc2626); }
    .share-btn[disabled] { opacity: .55; cursor: default; }
    .share-btn:focus-visible, .share-close:focus-visible { outline: 3px solid var(--primary, #058028); outline-offset: 2px; }

    /* The layouts' Bootstrap already hides [hidden]; this keeps it so without it. */
    .share-dialog [hidden] { display: none !important; }

    .share-new { display: flex; flex-direction: column; gap: 10px; padding: 12px; border-radius: 10px; background: var(--primary-dim, #e9f5ee); }
    .share-new .share-field label { color: var(--primary-dark, #046322); }
    .share-new input { background: var(--card, #fff); }

    .share-links h3 { margin: 0 0 6px; font-size: .8rem; font-weight: 600; color: var(--text-2, #6b7280); text-transform: uppercase; letter-spacing: .04em; }
    .share-links ul { margin: 0; padding: 0; list-style: none; border: 1px solid var(--border, #e8eaf0); border-radius: 10px; }
    .share-links li { display: flex; align-items: center; gap: 8px; padding: 10px 12px; }
    .share-links li + li { border-top: 1px solid var(--border, #e8eaf0); }
    .share-link-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; font-size: .82rem; }
    .share-link-text span:last-child { color: var(--text-2, #6b7280); font-size: .78rem; }
    .share-empty { margin: 0; font-size: .82rem; color: var(--text-3, #9ca3af); }

    @media (max-width: 480px) {
        .share-make { flex-direction: column; align-items: stretch; }
        .share-links li { flex-wrap: wrap; }
    }
    @media (prefers-reduced-motion: reduce) {
        .share-dialog[open] { animation: none; }
    }
</style>

<dialog class="share-dialog" id="shareDialog" aria-labelledby="shareDialogTitle">
    <div class="share-box">
        <div class="share-head">
            <div>
                <h2 class="share-title" id="shareDialogTitle">Share a link</h2>
                <p class="share-file" id="shareDialogFile"></p>
            </div>
            <button type="button" class="share-close" id="shareDialogClose" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        {{-- Messages go here, not in a toast: the open dialog dims everything behind it. --}}
        <p class="share-note" id="shareStatus" role="status" hidden></p>

        <p class="share-intro">Anyone who has the link can open and download this file without signing in. It stops working on the date you pick, or as soon as you turn it off.</p>

        <p class="share-note amber" id="sharePending" hidden>This file is still subject for approval. The page the link opens will say it is a draft.</p>
        <p class="share-note amber" id="shareBlocked" hidden></p>

        <div class="share-make" id="shareMake" hidden>
            <div class="share-field">
                <label for="shareDays">Link works for</label>
                <select id="shareDays"></select>
            </div>
            <button type="button" class="share-btn primary" id="shareMakeBtn"><i class="bi bi-link-45deg" aria-hidden="true"></i> Make link</button>
        </div>

        <div class="share-new" id="shareNew" hidden>
            <div class="share-field">
                <label for="shareNewUrl">Your new link - copy it and send it</label>
                <div class="share-url-row">
                    <input type="text" id="shareNewUrl" readonly>
                    <button type="button" class="share-btn primary" id="shareNewCopy">Copy</button>
                </div>
            </div>
            {{-- The computer's own share panel (Messenger, Mail, Nearby sharing... whatever
                 it offers). Only where the browser has one: Chrome/Edge on Windows, Safari
                 on a Mac - and only on https. --}}
            <button type="button" class="share-btn" id="shareNewSend" hidden><i class="bi bi-share" aria-hidden="true"></i> Send with an app…</button>
        </div>

        <div class="share-links">
            <h3>Links that work now</h3>
            <ul id="shareList" hidden></ul>
            <p class="share-empty" id="shareEmpty">Loading…</p>
        </div>
    </div>
</dialog>

<script>
    (function () {
        const dialog = document.getElementById('shareDialog');
        if (! dialog) return;

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const $ = id => document.getElementById(id);
        const fileName = $('shareDialogFile'), pending = $('sharePending'), blocked = $('shareBlocked');
        const make = $('shareMake'), days = $('shareDays'), makeBtn = $('shareMakeBtn');
        const fresh = $('shareNew'), freshUrl = $('shareNewUrl'), freshCopy = $('shareNewCopy'), freshSend = $('shareNewSend');
        const list = $('shareList'), empty = $('shareEmpty'), status = $('shareStatus');

        let url = null;   // the open file's share-links address
        let turn = 0;     // which opening a reply belongs to, so a late one is dropped

        function say(message, type) {
            status.textContent = message;
            status.className = 'share-note ' + (type === 'error' ? 'red' : 'green');
            status.hidden = false;
        }

        function request(address, options) {
            return fetch(address, Object.assign({
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            }, options)).then(response => response.json().catch(() => ({})).then(body => {
                if (! response.ok) throw new Error(body.message || 'Something went wrong. Please try again.');
                return body;
            }));
        }

        // The Clipboard API only exists on https (or localhost); a plain
        // http address on the school network gets the older way.
        function copy(text, button) {
            const done = () => {
                const label = button.textContent;
                button.textContent = 'Copied';
                setTimeout(() => { button.textContent = label; }, 1600);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done, () => fallback(text) && done());
            } else if (fallback(text)) {
                done();
            } else {
                say('Copying did not work here. Select the link and copy it yourself.', 'error');
            }
        }

        function fallback(text) {
            const box = document.createElement('textarea');
            box.value = text;
            box.setAttribute('readonly', '');
            box.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
            dialog.appendChild(box);   // inside the dialog: the modal makes the rest of the page inert
            box.select();
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { /* not supported */ }
            box.remove();
            return ok;
        }

        function draw(data) {
            pending.hidden = ! data.pending;
            blocked.hidden = data.can_create;
            blocked.textContent = data.reason || '';
            make.hidden = ! data.can_create;

            if (! days.options.length) {
                data.durations.forEach(d => days.add(new Option(d.label, d.days, d.default, d.default)));
            }

            list.replaceChildren();
            data.links.forEach(link => list.appendChild(row(link)));
            list.hidden = ! data.links.length;
            empty.hidden = !! data.links.length;
            empty.textContent = 'No links yet.';
        }

        function row(link) {
            const item = document.createElement('li');

            const text = document.createElement('div');
            text.className = 'share-link-text';
            const who = document.createElement('span');
            who.textContent = (link.mine ? 'Made by you' : 'Made by ' + (link.made_by || 'a former officer')) + ' · until ' + link.until;
            const count = document.createElement('span');
            count.textContent = 'Opened ' + link.opened + (link.opened === 1 ? ' time' : ' times')
                + ' · ' + link.downloads + (link.downloads === 1 ? ' download' : ' downloads');
            text.append(who, count);
            item.appendChild(text);

            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'share-btn small';
            copyBtn.textContent = 'Copy';
            copyBtn.addEventListener('click', () => copy(link.url, copyBtn));
            item.appendChild(copyBtn);

            if (link.can_turn_off) {
                const off = document.createElement('button');
                off.type = 'button';
                off.className = 'share-btn small danger';
                off.textContent = 'Turn off';
                off.addEventListener('click', () => turnOff(link, off));
                item.appendChild(off);
            }

            return item;
        }

        function load() {
            const mine = turn;
            request(url).then(data => { if (mine === turn) draw(data); })
                .catch(error => { if (mine === turn) { empty.textContent = error.message; empty.hidden = false; } });
        }

        function turnOff(link, button) {
            const ask = window.confirmAction
                ? window.confirmAction({ title: 'Turn off this link?', message: 'Anyone who has it will no longer be able to open the file.', ok: 'Turn off', tone: 'warning' })
                : Promise.resolve(confirm('Turn off this link?'));

            ask.then(yes => {
                if (! yes) return;
                // confirmAction closes its own dialog; ours stays open behind it.
                button.disabled = true;
                request(link.turn_off_url, { method: 'DELETE' })
                    .then(() => {
                        if (freshUrl.value === link.url) fresh.hidden = true;
                        say('Link turned off.');
                        load();
                    })
                    .catch(error => { button.disabled = false; say(error.message, 'error'); });
            });
        }

        makeBtn.addEventListener('click', () => {
            const mine = turn;
            makeBtn.disabled = true;
            status.hidden = true;
            request(url, { method: 'POST', body: JSON.stringify({ days: Number(days.value) }) })
                .then(data => {
                    if (mine !== turn) return;
                    freshUrl.value = data.link.url;
                    fresh.hidden = false;
                    freshUrl.focus();
                    freshUrl.select();
                    load();
                })
                .catch(error => say(error.message, 'error'))
                .finally(() => { makeBtn.disabled = false; });
        });

        freshCopy.addEventListener('click', () => copy(freshUrl.value, freshCopy));

        freshSend.hidden = typeof navigator.share !== 'function';
        freshSend.addEventListener('click', () => {
            navigator.share({ title: fileName.textContent, url: freshUrl.value }).catch((error) => {
                if (error && error.name !== 'AbortError') say('Sending with an app did not work here. Copy the link instead.', 'error');
            });
        });
        $('shareDialogClose').addEventListener('click', () => dialog.close());
        // The dialog itself has no padding, so a click that lands on it is on the backdrop.
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });

        document.addEventListener('click', e => {
            const trigger = e.target.closest('[data-share-file]');
            if (! trigger) return;
            e.preventDefault();

            turn++;
            url = trigger.dataset.shareUrl;
            fileName.textContent = trigger.dataset.shareName || '';
            [status, pending, blocked, make, fresh, list].forEach(node => { node.hidden = true; });
            freshUrl.value = '';
            empty.textContent = 'Loading…';
            empty.hidden = false;

            if (! dialog.open) dialog.showModal();
            load();
        });
    })();
</script>
