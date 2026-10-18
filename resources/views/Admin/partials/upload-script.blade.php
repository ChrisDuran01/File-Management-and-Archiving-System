{{-- Upload modal -> corner toast with per-file progress. Included inside a
     <script> on the Folders page and on a folder's page.

     Every file goes through this server (files.store), one at a time: the
     server keeps a local copy and puts the file in cloud storage. Files
     used to go straight from the browser to Supabase; the Backblaze bucket
     doesn't take uploads from browsers, and one path is simpler to trust. --}}
(function setupUpload(formSelector, modalId) {
    const form = document.querySelector(formSelector);
    const modalEl = document.getElementById(modalId);
    const toast = document.getElementById('uploadToast');
    const toastList = document.getElementById('uploadToastList');
    const toastClose = document.getElementById('uploadToastClose');
    if (!form || !modalEl || !toast || !toastList) return;

    const storeUrl = @json(route('files.store'));
    const csrfToken = form.querySelector('[name="_token"]').value;
    const scanCheckbox = form.querySelector('[name="scan_as_document"]');
    const pendingCheckbox = form.querySelector('[name="pending_approval"]');

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
            if (ui.xhr) ui.xhr.abort();
            finish(ui, 'Cancelled', false);
        });

        return ui;
    }

    function finish(ui, text, ok) {
        ui.status.textContent = text;
        ui.bar.classList.remove('is-indeterminate');
        ui.bar.style.width = '100%';
        ui.bar.classList.add(ok ? 'is-done' : 'is-failed');
        ui.cancelBtn.style.display = 'none';
    }

    function upload(file, folderId, ui) {
        if (ui.cancelled) return Promise.resolve();

        ui.status.textContent = '0%';

        return new Promise(function (resolve) {
            const fd = new FormData();
            fd.append('files[]', file);
            if (folderId) fd.append('folder_id', folderId);
            if (scanCheckbox && scanCheckbox.checked) fd.append('scan_as_document', '1');
            if (pendingCheckbox && pendingCheckbox.checked) fd.append('pending_approval', '1');
            fd.append('_token', csrfToken);

            const xhr = new XMLHttpRequest();
            ui.xhr = xhr;

            xhr.upload.addEventListener('progress', function (evt) {
                if (! evt.lengthComputable || ui.cancelled) return;
                const percent = Math.round((evt.loaded / evt.total) * 100);
                ui.bar.style.width = percent + '%';
                if (percent < 100) {
                    ui.status.textContent = percent + '%';
                } else {
                    // Bytes are here; the server is now putting it in the cloud.
                    ui.status.textContent = 'Saving…';
                    ui.bar.classList.add('is-indeterminate');
                }
            });

            // The server answers in JSON (files.store sees Accept), so a
            // refused file - wrong type, too big, read-only folder - shows
            // as failed here instead of looking saved.
            xhr.addEventListener('load', function () {
                if (ui.cancelled) { resolve(); return; }
                let body = {};
                try { body = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                if (xhr.status >= 200 && xhr.status < 300) {
                    finish(ui, body.cloudPending ? 'Saved (cloud copy pending)' : 'Done ✓', true);
                } else {
                    const reason = body.message || (body.errors && Object.values(body.errors)[0][0]) || 'Upload failed';
                    finish(ui, reason, false);
                }
                resolve();
            });
            xhr.addEventListener('error', function () {
                if (! ui.cancelled) finish(ui, 'Upload failed - check the connection', false);
                resolve();
            });
            xhr.addEventListener('abort', function () { resolve(); });

            xhr.open('POST', storeUrl, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(fd);
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

        toast.style.display = 'block';
        toastList.innerHTML = '';
        const rows = files.map(function (file) { return { file: file, ui: addRow(file.name) }; });

        // One at a time: a long batch doesn't tie up the server, and each
        // file's bar moves in turn.
        rows.reduce(function (previous, entry) {
            return previous.then(function () { return upload(entry.file, folderId, entry.ui); });
        }, Promise.resolve())
        .finally(function () {
            const anyFailed = rows.some(function (entry) { return ! entry.ui.cancelled && entry.ui.bar.classList.contains('is-failed'); });
            // Leave a failure on screen to be read; otherwise show the new files.
            if (! anyFailed) setTimeout(function () { window.location.reload(); }, 1200);
        });
    });
})('#uploadModal form', 'uploadModal');
