{{-- The upload modal's file list: picking files (Click to browse) and
     dropping them on the zone ADD to what is already chosen - the browser
     on its own replaces the whole choice each time. The same file twice is
     kept once, and each chosen file has a × to take it back out. The
     input's FileList is rebuilt from this list with DataTransfer, so the
     upload (Admin/partials/upload-script) sends exactly what is shown.
     Included inside a <script> on the Folders page and on a folder's page. --}}
(function setupUploadPicker(zoneId, inputId, listId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    if (!zone || !input || !list) return;

    const css = document.createElement('style');
    css.textContent =
        '.picked-files{max-height:120px;overflow-y:auto;border:1px solid var(--border,#eee);border-radius:8px;padding:6px 10px;margin-top:6px}' +
        '.picked-file{display:flex;align-items:center;gap:4px;padding:3px 0;border-bottom:1px solid var(--border,#f0f0f0)}' +
        '.picked-file:last-child{border-bottom:none}' +
        '.picked-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
        '.picked-remove{margin-left:auto;flex-shrink:0;border:none;background:none;color:var(--text-2,#666);font-size:16px;line-height:1;padding:0 4px;border-radius:4px;cursor:pointer}' +
        '.picked-remove:hover,.picked-remove:focus-visible{color:var(--red,#a32d2d)}';
    document.head.append(css);

    let picked = [];
    const key = f => f.name + '|' + f.size + '|' + f.lastModified;

    function add(files) {
        const have = new Set(picked.map(key));
        Array.from(files).forEach(function (f) {
            if (!have.has(key(f))) { picked.push(f); have.add(key(f)); }
        });
        sync();
    }

    function sync() {
        const dt = new DataTransfer();
        picked.forEach(f => dt.items.add(f));
        input.files = dt.files;
        render();
    }

    function render() {
        list.innerHTML = '';
        if (!picked.length) return;

        const box = document.createElement('div');
        box.className = 'picked-files';
        picked.forEach(function (f, i) {
            const row = document.createElement('div');
            row.className = 'picked-file';
            const name = document.createElement('span');
            name.className = 'picked-name';
            name.innerHTML = '<i class="bi bi-file-earmark me-1" aria-hidden="true"></i>';
            name.append(f.name); // a file name is text, never markup
            const size = document.createElement('span');
            size.className = 'text-muted ms-1';
            size.textContent = '(' + (f.size / 1024).toFixed(1) + ' KB)';
            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'picked-remove';
            x.innerHTML = '&times;';
            x.title = 'Remove from this upload';
            x.setAttribute('aria-label', 'Remove ' + f.name + ' from this upload');
            x.addEventListener('click', function (e) {
                e.stopPropagation();
                picked.splice(i, 1);
                sync();
                const next = list.querySelectorAll('.picked-remove')[Math.min(i, picked.length - 1)];
                (next || zone).focus();
            });
            row.append(name, size, x);
            box.append(row);
        });
        list.append(box);
    }

    input.addEventListener('change', function () {
        // The browser just put only the new pick in the input; add it to the rest.
        add(input.files);
    });

    // Without this, dropping files anywhere the browser doesn't explicitly
    // handle makes it navigate to/download the file instead of accepting it.
    window.addEventListener('dragover', e => e.preventDefault());
    window.addEventListener('drop', e => e.preventDefault());

    ['dragenter', 'dragover'].forEach(evt => {
        zone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('drag-over');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        zone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');
        });
    });

    zone.addEventListener('drop', function (e) {
        if (e.dataTransfer.files && e.dataTransfer.files.length) add(e.dataTransfer.files);
    });

    // Enter or Space on the focused zone opens the file picker, like a button.
    zone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input.click();
        }
    });
})('uploadZone', 'fileInput', 'fileNameList');
