{{-- Upload modal -> corner toast with per-file progress. Included inside a
     <script> on the Folders page and on a folder's page.

     Every file goes through this server (files.store), one at a time: the
     server keeps a local copy and puts the file in cloud storage. Files
     used to go straight from the browser to Supabase; the Backblaze bucket
     doesn't take uploads from browsers, and one path is simpler to trust.

     Before anything is sent, the names are checked against the folder
     (files.nameCheck). For a file whose name is already there, the page's
     partials/file-versions asks: save it as the next version of that file,
     keep both ("(1)", as before), or don't upload it. Without that partial
     every file is saved as before. --}}
(function setupUpload(formSelector, modalId) {
    const form = document.querySelector(formSelector);
    const modalEl = document.getElementById(modalId);
    const toast = document.getElementById('uploadToast');
    const toastList = document.getElementById('uploadToastList');
    const toastClose = document.getElementById('uploadToastClose');
    if (!form || !modalEl || !toast || !toastList) return;

    const storeUrl = @json(route('files.store'));
    const nameCheckUrl = @json(route('files.nameCheck'));
    const csrfToken = form.querySelector('[name="_token"]').value;
    const scanCheckbox = form.querySelector('[name="scan_as_document"]');
    const pendingCheckbox = form.querySelector('[name="pending_approval"]');

    // ---- One bar, 0-100%, for the whole upload ----
    // Two steps: the browser sending the file here (measured), then this
    // server putting it in cloud storage (the browser can't see that, so it
    // is estimated from how long earlier files took per MB). The shown
    // percentage starts slow and speeds up toward the end, and runs to 100%
    // the moment the server answers. The estimate is on the generous side,
    // so that answer usually comes early - which feels fast.
    const SEND_SHARE = 0.3;      // part of the bar for sending the file here
    const SAVE_BASE_MS = 1200;   // saving overhead regardless of size
    const EASE = 1.6;            // > 1: slow start, fast finish
    let msPerMb = 700;           // learned below, kept for the next visit
    try { msPerMb = Number(localStorage.getItem('upload-ms-per-mb')) || msPerMb; } catch (e) { /* storage blocked */ }

    function showPercent(ui, pct) {
        ui.bar.style.width = pct + '%';
        ui.status.textContent = pct + '%';
    }

    function startProgress(ui, file) {
        const sizeMb = file.size / 1048576;
        const p = ui.progress = {
            sent: 0, sentAt: 0, shown: 0, raf: 0, sizeMb: sizeMb,
            expectMs: 1.3 * (SAVE_BASE_MS + msPerMb * sizeMb),
        };
        (function tick() {
            let actual = SEND_SHARE * p.sent;
            if (p.sentAt) {
                // Linear up to the estimate, then creeping, never quite done.
                const t = (performance.now() - p.sentAt) / p.expectMs;
                const saved = t < 1 ? 0.92 * t : 0.92 + 0.06 * (1 - Math.exp(1 - t));
                actual = SEND_SHARE + (1 - SEND_SHARE) * saved;
            }
            p.shown = Math.max(p.shown, Math.min(97, Math.round(100 * Math.pow(actual, EASE))));
            showPercent(ui, p.shown);
            p.raf = requestAnimationFrame(tick);
        })();
    }

    function markSent(ui) {
        const p = ui.progress;
        if (! p || p.sentAt || ui.cancelled) return;
        p.sent = 1;
        p.sentAt = performance.now();
        ui.bar.classList.add('is-indeterminate'); // moving stripes while the server saves it
        // A new version can't be taken back from here once it is being saved.
        if (ui.isVersion) ui.cancelBtn.style.display = 'none';
    }

    // Learn how long saving took for the next file's estimate.
    function learn(p) {
        if (! p.sentAt) return;
        const savedMs = performance.now() - p.sentAt;
        const observed = Math.max(50, (savedMs - SAVE_BASE_MS) / Math.max(p.sizeMb, 0.25));
        msPerMb = Math.round(0.6 * msPerMb + 0.4 * observed);
        try { localStorage.setItem('upload-ms-per-mb', String(msPerMb)); } catch (e) { /* storage blocked */ }
    }

    // The server answered: run the rest of the way quickly, then finish.
    function sprint(ui, done) {
        const p = ui.progress;
        cancelAnimationFrame(p.raf);
        const from = p.shown;
        const start = performance.now();
        (function step() {
            const k = Math.min(1, (performance.now() - start) / 300);
            showPercent(ui, Math.round(from + (100 - from) * k));
            if (k < 1) { p.raf = requestAnimationFrame(step); } else { done(); }
        })();
    }

    if (toastClose) {
        toastClose.addEventListener('click', function () {
            toast.style.display = 'none';
        });
    }

    function addRow(name) {
        const row = document.createElement('div');
        row.className = 'upload-toast-row';
        row.innerHTML =
            '<div class="row-top">' +
                '<span class="row-name"></span>' +
                '<span class="row-status">Waiting…</span>' +
                '<button type="button" class="row-cancel-btn" title="Cancel upload"><i class="fas fa-times"></i></button>' +
            '</div>' +
            '<div class="row-track"><div class="row-bar"></div></div>';
        row.querySelector('.row-name').textContent = name; // a file name is text, never markup
        toastList.appendChild(row);

        const ui = {
            status: row.querySelector('.row-status'),
            bar: row.querySelector('.row-bar'),
            cancelBtn: row.querySelector('.row-cancel-btn'),
            xhr: null,
            cancelled: false,
        };

        ui.cancelBtn.addEventListener('click', function () {
            ui.cancelled = true;
            // Already here and being saved: stopping the browser wouldn't stop
            // the server, so wait for it and take the file back (takeBack).
            if (ui.xhr && ui.progress && ui.progress.sentAt) {
                stop(ui, 'Cancelling…');
                return;
            }
            if (ui.xhr) ui.xhr.abort();
            stop(ui, 'Cancelled');
        });

        return ui;
    }

    // Removes what a cancelled upload had already saved (FileController::cancelUpload).
    function takeBack(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.ok; }).catch(function () { return false; });
    }

    function finish(ui, text, ok) {
        if (ui.progress) cancelAnimationFrame(ui.progress.raf);
        ui.status.textContent = text;
        ui.bar.classList.remove('is-indeterminate');
        ui.bar.style.width = '100%';
        ui.bar.classList.add(ok ? 'is-done' : 'is-failed');
        ui.cancelBtn.style.display = 'none';
    }

    // Not uploaded after all - cancelled, or turned down at the same-name
    // question: the bar stops where it is and empties, not filled like a
    // finished or failed one.
    function stop(ui, text) {
        if (ui.progress) cancelAnimationFrame(ui.progress.raf);
        ui.status.textContent = text;
        ui.bar.classList.remove('is-indeterminate');
        ui.bar.style.width = '0%';
        ui.cancelBtn.style.display = 'none';
    }

    // A file the officer chose not to upload at the same-name question.
    function skip(ui) {
        stop(ui, 'Not uploaded');
    }

    // version: { url, note, next } when the file is saved as the next
    // version of the one already in the folder (FileVersionController::store).
    function upload(file, folderId, ui, version) {
        if (ui.cancelled) return Promise.resolve();

        ui.isVersion = !! version;
        startProgress(ui, file);

        return new Promise(function (resolve) {
            const fd = new FormData();
            if (version) {
                fd.append('file', file);
                fd.append('note', version.note || '');
            } else {
                fd.append('files[]', file);
                if (folderId) fd.append('folder_id', folderId);
                if (scanCheckbox && scanCheckbox.checked) fd.append('scan_as_document', '1');
                if (pendingCheckbox && pendingCheckbox.checked) fd.append('pending_approval', '1');
            }
            fd.append('_token', csrfToken);

            const xhr = new XMLHttpRequest();
            ui.xhr = xhr;

            xhr.upload.addEventListener('progress', function (evt) {
                if (! evt.lengthComputable || ui.cancelled) return;
                ui.progress.sent = evt.loaded / evt.total;
                // Bytes are all here; the server is now putting it in the cloud.
                if (evt.loaded >= evt.total) markSent(ui);
            });
            xhr.upload.addEventListener('load', function () { markSent(ui); });

            // The server answers in JSON (files.store sees Accept), so a
            // refused file - wrong type, too big, read-only folder - shows
            // as failed here instead of looking saved.
            xhr.addEventListener('load', function () {
                let body = {};
                try { body = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                const saved = xhr.status >= 200 && xhr.status < 300;
                if (ui.cancelled) {
                    if (! saved || ! body.cancel_url) { stop(ui, 'Cancelled'); resolve(); return; }
                    takeBack(body.cancel_url).then(function (ok) {
                        if (ok) {
                            stop(ui, 'Cancelled');
                        } else {
                            // Not taken back: say so, and keep it on screen (no reload).
                            ui.cancelled = false;
                            finish(ui, 'Couldn’t cancel - it was saved', false);
                        }
                        resolve();
                    });
                    return;
                }
                if (saved) {
                    // Saved already - too late to cancel.
                    ui.cancelBtn.style.display = 'none';
                    learn(ui.progress);
                    sprint(ui, function () {
                        finish(ui, version ? 'Saved as version ' + (body.version_no || version.next) + ' ✓'
                            : body.cloudPending ? 'Saved (cloud copy pending)' : 'Done ✓', true);
                        // An exact copy of a file already saved (DuplicateDetector).
                        (body.copies || []).forEach(function (c) {
                            const note = document.createElement('div');
                            note.className = 'row-note';
                            note.textContent = c.original
                                ? 'Same file as “' + c.original + '”' + (c.folder ? ' in ' + c.folder : '') + '. It is marked as a possible copy.'
                                : 'Same file as one already saved in a folder you can’t open.';
                            ui.status.closest('.upload-toast-row').appendChild(note);
                        });
                        resolve();
                    });
                    return;
                }
                const reason = body.message || (body.errors && Object.values(body.errors)[0][0]) || 'Upload failed';
                finish(ui, reason, false);
                resolve();
            });
            xhr.addEventListener('error', function () {
                if (! ui.cancelled) finish(ui, 'Upload failed - check the connection', false);
                resolve();
            });
            xhr.addEventListener('abort', function () { resolve(); });

            xhr.open('POST', version ? version.url : storeUrl, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(fd);
        });
    }

    // Which of these names are already in the folder. A failed check just
    // means every file is saved as before.
    function checkNames(files, folderId) {
        if (! window.fileVersions) return Promise.resolve({ existing: [] });

        return fetch(nameCheckUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ folder_id: folderId || null, names: files.map(function (f) { return f.name; }) }),
        }).then(function (r) { return r.ok ? r.json() : { existing: [] }; })
          .catch(function () { return { existing: [] }; });
    }

    // One question per clashing name, one after another, before any upload starts.
    function plan(files, folderId) {
        return checkNames(files, folderId).then(function (found) {
            const byName = {};
            (found.existing || []).forEach(function (x) { byName[x.name] = x; });

            return files.reduce(function (previous, file) {
                return previous.then(function (list) {
                    const existing = byName[file.name];
                    if (! existing) return list.concat([{ file: file, mode: 'new' }]);

                    return window.fileVersions.ask(existing, found.folder).then(function (a) {
                        return list.concat([{
                            file: file,
                            mode: a.choice,
                            version: a.choice === 'version' ? { url: existing.upload_url, note: a.note, next: existing.next_version } : null,
                        }]);
                    });
                });
            }, Promise.resolve([]));
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const fileInput = form.querySelector('input[type="file"]');
        const files = Array.from(fileInput.files || []);
        if (! files.length) return;

        const folderIdField = form.querySelector('[name="folder_id"]');
        const folderId = folderIdField ? folderIdField.value : '';

        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();

        plan(files, folderId).then(function (entries) { start(entries, folderId); });
    });

    function start(entries, folderId) {
        toast.style.display = 'block';
        toastList.innerHTML = '';
        const rows = entries.map(function (entry) {
            entry.ui = addRow(entry.file.name);
            if (entry.mode === 'skip') skip(entry.ui);
            return entry;
        });

        // One at a time: a long batch doesn't tie up the server, and each
        // file's bar moves in turn.
        rows.reduce(function (previous, entry) {
            return previous.then(function () {
                return entry.mode === 'skip' ? null : upload(entry.file, folderId, entry.ui, entry.version);
            });
        }, Promise.resolve())
        .finally(function () {
            const anyFailed = rows.some(function (entry) { return ! entry.ui.cancelled && entry.ui.bar.classList.contains('is-failed'); });
            // Leave a failure on screen to be read; otherwise show the new files.
            if (! anyFailed) setTimeout(function () { window.location.reload(); }, 1200);
        });
    }
})('#uploadModal form', 'uploadModal');
