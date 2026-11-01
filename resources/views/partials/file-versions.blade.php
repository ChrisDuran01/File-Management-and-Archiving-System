{{--
    File versions (FileVersionController, App\Services\FileVersions):

    - The Versions panel (along the right edge of the screen): every
      version of one file, newest first, with Open / Download / Make
      current, "Upload new version" and Mark as final. Any button opens it
      with:

          <button type="button" data-versions-file
                  data-versions-name="{{ $file->filename }}"
                  data-versions-url="{{ route('files.versions.index', $file->id) }}">

      Add data-versions-upload to open it at the upload form.

    - The question an upload asks when a file with the same name is already
      in the folder (Admin/partials/upload-script calls
      window.fileVersions.ask()).

    Native <dialog>s like partials/share-link, so they don't depend on the
    page loading Bootstrap's script. Included by Admin/showFolder,
    Admin/folders and Admin/preview.
--}}
<style>
    .ver-dialog {
        width: min(520px, calc(100vw - 32px));
        max-height: calc(100vh - 48px);
        padding: 0;
        border: 1px solid var(--border, #e8eaf0);
        border-radius: 16px;
        background: var(--card, #fff);
        color: var(--text-1, #111827);
        box-shadow: 0 24px 64px rgba(15, 23, 42, .28);
    }
    .ver-dialog::backdrop { background: rgba(15, 23, 42, .5); }
    .ver-dialog[open] { animation: ver-in .16s ease-out; }
    @keyframes ver-in { from { opacity: 0; transform: translateY(8px) scale(.98); } }
    .ver-box { padding: 22px 24px 24px; display: flex; flex-direction: column; gap: 14px; }

    .ver-head { display: flex; align-items: flex-start; gap: 12px; }
    .ver-head > div { flex: 1; min-width: 0; }
    .ver-title { margin: 0; font-size: 1.05rem; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; }
    .ver-file { margin: 2px 0 0; font-size: .85rem; color: var(--text-2, #4b5563); overflow-wrap: anywhere; }
    .ver-close {
        width: 34px; height: 34px; flex-shrink: 0;
        display: grid; place-items: center;
        border: 0; border-radius: 8px; background: transparent; color: var(--text-2, #4b5563);
    }
    .ver-close:hover { background: var(--nav-hover-bg, #f4f6f9); color: var(--text-1, #111827); }

    .ver-sub { margin: 0; font-size: .85rem; color: var(--text-2, #4b5563); }
    .ver-note { margin: 0; padding: 10px 12px; border-radius: 10px; font-size: .82rem; line-height: 1.45; }
    .ver-note.amber { background: var(--warning-dim, #fffbeb); color: var(--warning, #b45309); }
    .ver-note.green { background: var(--success-dim, #e9f5ee); color: var(--success-text, #046322); }
    .ver-note.red { background: var(--danger-dim, #fef2f2); color: var(--danger, #c81e1e); }

    .ver-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 10px; }
    .ver-item { border: 1px solid var(--border, #e8eaf0); border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; gap: 5px; }
    .ver-item.current { border-color: var(--primary, #058028); }
    .ver-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ver-no { font-family: 'DM Mono', ui-monospace, monospace; font-weight: 500; font-size: .9rem; }
    .ver-meta { font-size: .78rem; color: var(--text-3, #646b78); overflow-wrap: anywhere; }
    .ver-text { font-size: .86rem; overflow-wrap: anywhere; }
    .ver-text.none { color: var(--text-3, #646b78); font-style: italic; }
    .ver-acts { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 3px; }
    .ver-empty { margin: 0; font-size: .82rem; color: var(--text-3, #646b78); }

    .ver-upload { display: flex; flex-direction: column; gap: 10px; border-top: 1px solid var(--border, #e8eaf0); padding-top: 14px; }
    .ver-upload h3 { margin: 0; font-size: .8rem; font-weight: 600; color: var(--text-2, #4b5563); text-transform: uppercase; letter-spacing: .04em; }
    .ver-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
    .ver-field label { font-size: .8rem; font-weight: 600; }
    .ver-field label span { font-weight: 400; color: var(--text-3, #646b78); }
    .ver-field input[type="text"],
    .ver-field input[type="file"] {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid var(--border, #e8eaf0); border-radius: 8px;
        background: var(--surface, #f7f8fc); color: var(--text-1, #111827);
        font-size: .875rem;
    }
    .ver-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .ver-foot { margin: 0; font-size: .78rem; color: var(--text-3, #646b78); }

    .ver-btn {
        min-height: 36px; padding: 0 14px;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        border: 1px solid var(--border, #e8eaf0); border-radius: 8px;
        background: var(--card, #fff); color: var(--text-1, #111827);
        font-size: .84rem; font-weight: 600; white-space: nowrap; cursor: pointer; text-decoration: none;
    }
    .ver-btn:hover { background: var(--nav-hover-bg, #f4f6f9); color: var(--text-1, #111827); }
    /* Fixed colours: keep white text readable in light and dark mode. */
    .ver-btn.primary { border-color: transparent; background: #058028; color: #fff; }
    .ver-btn.primary:hover { background: #046322; color: #fff; }
    .ver-btn.small { min-height: 30px; padding: 0 10px; font-size: .78rem; }
    .ver-btn.plain { border-color: transparent; background: transparent; color: var(--text-2, #4b5563); }
    .ver-btn[disabled] { opacity: .55; cursor: default; }
    .ver-btn:focus-visible, .ver-close:focus-visible { outline: 3px solid var(--primary, #058028); outline-offset: 2px; }

    /* The Versions list is a panel along the right edge of the screen, full
       height, so the folder stays in view beside it. */
    .ver-dialog.ver-side {
        position: fixed;
        inset: 0 0 0 auto;
        margin: 0;
        width: min(440px, 100vw);
        height: 100vh;
        height: 100dvh;
        max-height: none;
        border-width: 0 0 0 1px;
        border-radius: 0;
        box-shadow: -12px 0 40px rgba(15, 23, 42, .18);
    }
    .ver-dialog.ver-side::backdrop { background: rgba(15, 23, 42, .28); }
    .ver-dialog.ver-side[open] { display: flex; flex-direction: column; animation: ver-slide .2s ease-out; }
    @keyframes ver-slide { from { opacity: 0; transform: translateX(32px); } }
    .ver-side .ver-box { flex: 1; min-height: 0; overflow-y: auto; padding-top: max(22px, env(safe-area-inset-top, 0px)); }
    /* Upload and Mark as final sit at the bottom of the panel. */
    .ver-side .ver-upload { margin-top: auto; }

    .ver-dialog [hidden] { display: none !important; }

    @media (max-width: 480px) {
        .ver-box { padding: 18px 16px 20px; }
        .ver-actions .ver-btn { flex: 1 1 100%; }
    }
    @media (prefers-reduced-motion: reduce) {
        .ver-dialog[open], .ver-dialog.ver-side[open] { animation: none; }
    }
</style>

<dialog class="ver-dialog ver-side" id="versionsDialog" aria-labelledby="versionsTitle">
    <div class="ver-box">
        <div class="ver-head">
            <div>
                <h2 class="ver-title" id="versionsTitle">Versions</h2>
                <p class="ver-file" id="versionsFile"></p>
            </div>
            <button type="button" class="ver-close" id="versionsClose" aria-label="Close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        {{-- Messages go here, not in a toast: the open dialog dims everything behind it. --}}
        <p class="ver-note" id="versionsStatus" role="status" hidden></p>
        <p class="ver-sub" id="versionsSummary"></p>
        <p class="ver-note amber" id="versionsLocked" hidden></p>

        <ol class="ver-list" id="versionsList"></ol>
        <p class="ver-empty" id="versionsEmpty">Loading…</p>

        <form class="ver-upload" id="versionsUpload" hidden>
            <h3>Upload a new version</h3>
            <div class="ver-field">
                <label for="versionsFileInput">File</label>
                <input type="file" id="versionsFileInput" required>
            </div>
            <div class="ver-field">
                <label for="versionsNoteInput">What changed? <span>(optional)</span></label>
                <input type="text" id="versionsNoteInput" maxlength="250" placeholder="e.g. Fixed margins per Adviser">
            </div>
            <div class="ver-actions">
                <button type="submit" class="ver-btn primary" id="versionsUploadBtn">Save as new version</button>
                <button type="button" class="ver-btn" id="versionsFinalBtn"></button>
            </div>
            <p class="ver-foot">The current version is kept in this list. Nothing is deleted.</p>
        </form>
    </div>
</dialog>

<dialog class="ver-dialog" id="sameNameDialog" aria-labelledby="sameNameTitle">
    <div class="ver-box">
        <h2 class="ver-title" id="sameNameTitle"></h2>
        <p class="ver-note amber" id="sameNameFinal" hidden></p>
        <div class="ver-field">
            <label for="sameNameNote">What changed? <span>(optional)</span></label>
            <input type="text" id="sameNameNote" maxlength="250" placeholder="e.g. Fixed margins per Adviser">
        </div>
        <div class="ver-actions">
            <button type="button" class="ver-btn primary" id="sameNameVersion"></button>
            <button type="button" class="ver-btn" id="sameNameBoth">Keep both as separate files</button>
            <button type="button" class="ver-btn plain" id="sameNameSkip">Don’t upload this file</button>
        </div>
        <p class="ver-foot" id="sameNameFoot"></p>
    </div>
</dialog>

<script>
    (function () {
        const $ = id => document.getElementById(id);
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        // Asked before something replaces a version marked final.
        function confirmFinal(finalNo, ok) {
            const message = 'A new version becomes the current one. The Final label stays on version ' + finalNo
                + ', so anyone can still see which one was accepted.';
            return window.confirmAction
                ? window.confirmAction({ title: 'Version ' + finalNo + ' is marked final', message: message, ok: ok, tone: 'warning' })
                : Promise.resolve(window.confirm('Version ' + finalNo + ' is marked final. ' + message));
        }

        function request(address, options) {
            return fetch(address, Object.assign({
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            }, options)).then(response => response.json().catch(() => ({})).then(body => {
                if (! response.ok) {
                    throw new Error(body.message || (body.errors && Object.values(body.errors)[0][0]) || 'Something went wrong. Please try again.');
                }
                return body;
            }));
        }

        /* ---------- The Versions dialog ---------- */
        const dialog = $('versionsDialog');
        const fileName = $('versionsFile'), status = $('versionsStatus'), summary = $('versionsSummary');
        const locked = $('versionsLocked'), list = $('versionsList'), empty = $('versionsEmpty');
        const form = $('versionsUpload'), fileInput = $('versionsFileInput'), noteInput = $('versionsNoteInput');
        const uploadBtn = $('versionsUploadBtn'), finalBtn = $('versionsFinalBtn');

        let url = null;       // the open file's versions address
        let data = null;      // what it answered last
        let turn = 0;         // which opening a reply belongs to, so a late one is dropped
        let changed = false;  // the folder list behind is out of date once anything changed

        function say(message, type) {
            status.textContent = message;
            status.className = 'ver-note ' + (type === 'error' ? 'red' : 'green');
            status.hidden = false;
        }

        function button(label, cls) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ver-btn small ' + (cls || '');
            b.textContent = label;
            return b;
        }

        function link(label, href, download) {
            const a = document.createElement('a');
            a.className = 'ver-btn small';
            a.href = href;
            a.textContent = label;
            if (! download) { a.target = '_blank'; a.rel = 'noopener'; }
            return a;
        }

        function chip(label, cls, icon) {
            const span = document.createElement('span');
            span.className = 'chip ' + (cls || '');
            if (icon) {
                const i = document.createElement('i');
                i.className = 'bi ' + icon;
                i.setAttribute('aria-hidden', 'true');
                span.append(i, ' ');
            }
            span.append(label);
            return span;
        }

        function draw(body) {
            data = body;
            fileName.textContent = body.name;

            const count = body.versions.length + body.missing;
            summary.textContent = count + (count === 1 ? ' version' : ' versions') + ' · version ' + body.version_no + ' is current'
                + (body.final_note ? ' · ' + body.final_note : '');

            locked.hidden = body.can_change;
            locked.textContent = body.reason || '';

            list.replaceChildren();
            body.versions.forEach(v => list.appendChild(row(v)));
            list.hidden = false;

            empty.hidden = ! body.missing;
            empty.textContent = body.missing
                ? (body.missing === 1 ? 'Version 1 is' : 'Versions 1–' + body.missing + ' are')
                    + ' not kept here any more. They are inside the folder’s archive ZIP, in “Older versions”.'
                : '';

            form.hidden = ! body.can_change;
            fileInput.accept = body.accept;
            uploadBtn.textContent = 'Save as version ' + (body.version_no + 1);
            finalBtn.textContent = body.is_final ? 'Remove final mark' : 'Mark version ' + body.version_no + ' as final';
        }

        function row(v) {
            const item = document.createElement('li');
            item.className = 'ver-item' + (v.current ? ' current' : '');

            const top = document.createElement('div');
            top.className = 'ver-top';
            const no = document.createElement('span');
            no.className = 'ver-no';
            no.textContent = 'v' + v.no;
            top.appendChild(no);
            if (v.current) top.appendChild(chip('Current', 'neutral'));
            if (v.final) top.appendChild(chip('Final', '', 'bi-check2'));

            const meta = document.createElement('div');
            meta.className = 'ver-meta';
            meta.textContent = [v.date, v.by, v.size].filter(Boolean).join(' · ')
                + (data && v.name !== data.name ? ' · saved as ' + v.name : '');

            const text = document.createElement('div');
            text.className = 'ver-text' + (v.note ? '' : ' none');
            text.textContent = v.note || 'No note';

            const acts = document.createElement('div');
            acts.className = 'ver-acts';
            acts.append(link('Open', v.open_url, false), link('Download', v.download_url, true));
            if (v.restore_url) {
                const restore = button('Make current');
                restore.addEventListener('click', () => makeCurrent(v, restore));
                acts.appendChild(restore);
            }

            item.append(top, meta, text, acts);
            return item;
        }

        function load() {
            const mine = turn;
            return request(url).then(body => { if (mine === turn) draw(body); })
                .catch(error => { if (mine === turn) { empty.textContent = error.message; empty.hidden = false; } });
        }

        function done(body) {
            changed = true;
            say(body.message);
            return load();
        }

        function makeCurrent(v, btn) {
            const ask = data.is_final ? confirmFinal(data.final_version, 'Make version ' + v.no + ' current') : Promise.resolve(true);
            ask.then(yes => {
                if (! yes) return;
                btn.disabled = true;
                status.hidden = true;
                request(v.restore_url, { method: 'POST' })
                    .then(done)
                    .catch(error => { btn.disabled = false; say(error.message, 'error'); });
            });
        }

        form.addEventListener('submit', e => {
            e.preventDefault();
            if (! fileInput.files.length) return;

            const ask = data.is_final ? confirmFinal(data.final_version, 'Upload anyway') : Promise.resolve(true);
            ask.then(yes => {
                if (! yes) return;
                const fd = new FormData();
                fd.append('file', fileInput.files[0]);
                fd.append('note', noteInput.value);
                uploadBtn.disabled = true;
                uploadBtn.textContent = 'Uploading…';
                status.hidden = true;
                request(data.upload_url, { method: 'POST', body: fd })
                    .then(body => { fileInput.value = ''; noteInput.value = ''; return done(body); })
                    .catch(error => say(error.message, 'error'))
                    .finally(() => {
                        uploadBtn.disabled = false;
                        if (data) uploadBtn.textContent = 'Save as version ' + (data.version_no + 1);
                    });
            });
        });

        finalBtn.addEventListener('click', () => {
            finalBtn.disabled = true;
            status.hidden = true;
            request(data.final_url, { method: data.is_final ? 'DELETE' : 'POST' })
                .then(done)
                .catch(error => say(error.message, 'error'))
                .finally(() => { finalBtn.disabled = false; });
        });

        $('versionsClose').addEventListener('click', () => dialog.close());
        // The dialog itself has no padding, so a click that lands on it is on the backdrop.
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
        // The row's "v3" label and Final mark are drawn by the server.
        dialog.addEventListener('close', () => { if (changed) window.location.reload(); });

        // Capture phase: a folder row's "v3" label sits in a cell whose own
        // click opens the file, so that click is stopped here, before it
        // gets there. A menu item's click carries on, so Bootstrap still
        // closes the menu.
        document.addEventListener('click', e => {
            const trigger = e.target.closest('[data-versions-file]');
            if (! trigger) return;
            e.preventDefault();
            if (trigger.closest('td[onclick]')) e.stopPropagation();

            turn++;
            url = trigger.dataset.versionsUrl;
            data = null;
            fileName.textContent = trigger.dataset.versionsName || '';
            [status, locked, form].forEach(node => { node.hidden = true; });
            list.replaceChildren();
            summary.textContent = '';
            empty.textContent = 'Loading…';
            empty.hidden = false;

            if (! dialog.open) dialog.showModal();
            load().then(() => {
                if (trigger.hasAttribute('data-versions-upload') && ! form.hidden) fileInput.focus();
            });
        }, true);

        /* ---------- The question at upload ---------- */
        const ask = $('sameNameDialog');
        let answer = null;

        function settle(choice) {
            if (! answer) return;
            const reply = answer;
            answer = null;
            reply({ choice: choice, note: $('sameNameNote').value });
            ask.close();
        }

        $('sameNameVersion').addEventListener('click', () => settle('version'));
        $('sameNameBoth').addEventListener('click', () => settle('both'));
        $('sameNameSkip').addEventListener('click', () => settle('skip'));
        // Esc, or a click outside, uploads nothing rather than guessing.
        ask.addEventListener('cancel', e => { e.preventDefault(); settle('skip'); });
        ask.addEventListener('click', e => { if (e.target === ask) settle('skip'); });

        window.fileVersions = {
            /**
             * existing: one entry of FileVersionController::nameCheck()'s list.
             * Resolves { choice: 'version' | 'both' | 'skip', note }.
             */
            ask: function (existing, folderName) {
                $('sameNameTitle').textContent = '“' + existing.name + '” is already in ' + (folderName || 'this folder');
                $('sameNameVersion').textContent = 'Save as version ' + existing.next_version;
                $('sameNameNote').value = '';

                const finalNote = $('sameNameFinal');
                finalNote.hidden = ! existing.final_version;
                finalNote.textContent = existing.final_version
                    ? 'Version ' + existing.final_version + ' is marked final. A new version becomes the current one, and the Final label stays on version ' + existing.final_version + '.'
                    : '';

                const dot = existing.name.lastIndexOf('.');
                const stem = dot > 0 ? existing.name.slice(0, dot) : existing.name;
                $('sameNameFoot').textContent = 'Keep both saves it as “' + stem + ' (1)' + (dot > 0 ? existing.name.slice(dot) : '') + '”, like before.';

                return new Promise(resolve => {
                    answer = resolve;
                    ask.showModal();
                    $('sameNameVersion').focus();
                });
            },
        };
    })();
</script>
